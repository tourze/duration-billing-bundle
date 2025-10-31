<?php

namespace Tourze\DurationBillingBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\DurationBillingBundle\Contract\DurationBillingOrderRepositoryInterface;
use Tourze\DurationBillingBundle\Contract\DurationBillingProductRepositoryInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\BillingStartedEvent;
use Tourze\DurationBillingBundle\Event\OrderFrozenEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;
use Tourze\DurationBillingBundle\Exception\OrderNotFoundException;
use Tourze\DurationBillingBundle\Exception\ProductNotFoundException;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

readonly class DurationBillingService implements DurationBillingServiceInterface
{
    public function __construct(
        private DurationBillingProductRepositoryInterface $productRepository,
        private DurationBillingOrderRepositoryInterface $orderRepository,
        private OrderStateMachine $stateMachine,
        private PriceCalculator $priceCalculator,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function startBilling(int|string $productId, string $userId, array $options = []): DurationBillingOrder
    {
        $product = $this->productRepository->findById($productId);
        if (null === $product) {
            throw new ProductNotFoundException(sprintf('Product with ID %s not found', $productId));
        }

        $order = $this->createOrder($product, $userId);
        $this->setOrderAmount($order, $options);
        $this->setOrderMetadata($order, $options);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new BillingStartedEvent($order));

        return $order;
    }

    private function createOrder(DurationBillingProduct $product, string $userId): DurationBillingOrder
    {
        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId($userId);
        $order->setOrderCode($this->generateOrderCode());
        $order->setStartTime(new \DateTimeImmutable());

        return $order;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function setOrderAmount(DurationBillingOrder $order, array $options): void
    {
        $prepaidAmount = $this->validateAndParseAmount($options['prepaid_amount'] ?? 0.0);
        $order->setPrepaidAmount($prepaidAmount);

        if ($prepaidAmount > 0) {
            $order->setStatus(OrderStatus::PREPAID);
        } else {
            $order->setStatus(OrderStatus::ACTIVE);
        }
    }

    private function validateAndParseAmount(mixed $amount): float
    {
        if (!is_numeric($amount)) {
            return 0.0;
        }

        return (float) $amount;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function setOrderMetadata(DurationBillingOrder $order, array $options): void
    {
        if (!isset($options['metadata']) || !is_array($options['metadata'])) {
            return;
        }

        $metadata = [];
        foreach ($options['metadata'] as $key => $value) {
            if (is_string($key)) {
                $metadata[$key] = $value;
            }
        }
        $order->setMetadata($metadata);
    }

    private function generateOrderCode(): string
    {
        return sprintf(
            'ORD-%s-%s',
            date('YmdHis'),
            substr(uniqid(), -6)
        );
    }

    public function freezeBilling(int|string $orderId): DurationBillingOrder
    {
        $order = $this->orderRepository->findById($orderId);
        if (null === $order) {
            throw new OrderNotFoundException(sprintf('Order with ID %s not found', $orderId));
        }

        if (false === $this->stateMachine->canFreeze($order)) {
            throw new InvalidOrderStateException(sprintf('Cannot freeze order in %s state', $order->getStatus()->value));
        }

        // Calculate current price
        $currentMinutes = $this->calculateElapsedMinutes($order);
        $priceResult = $this->calculatePrice($order, $currentMinutes);

        // Update order
        $order->setActualAmount($priceResult->finalPrice);
        $this->stateMachine->transitionTo($order, OrderStatus::FROZEN);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(new OrderFrozenEvent($order));

        return $order;
    }

    private function calculateElapsedMinutes(DurationBillingOrder $order): int
    {
        $now = new \DateTimeImmutable();
        $start = $order->getStartTime();

        return (int) (($now->getTimestamp() - $start->getTimestamp()) / 60);
    }

    private function calculatePrice(DurationBillingOrder $order, int $minutes): PriceResult
    {
        $product = $order->getProduct();
        $pricingRule = $product->getPricingRule();

        return $this->priceCalculator->calculate($product, $pricingRule, $minutes);
    }

    public function resumeBilling(int|string $orderId): DurationBillingOrder
    {
        $order = $this->orderRepository->findById($orderId);
        if (null === $order) {
            throw new OrderNotFoundException(sprintf('Order with ID %s not found', $orderId));
        }

        if (false === $this->stateMachine->canResume($order)) {
            throw new InvalidOrderStateException(sprintf('Cannot resume order in %s state', $order->getStatus()->value));
        }

        // Calculate frozen duration and add to frozen minutes
        $frozenDuration = $this->calculateElapsedMinutes($order);
        $order->setFrozenMinutes($order->getFrozenMinutes() + $frozenDuration);

        $this->stateMachine->transitionTo($order, OrderStatus::ACTIVE);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    public function endBilling(int|string $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (null === $order) {
            throw new OrderNotFoundException(sprintf('Order with ID %s not found', $orderId));
        }

        if (false === $this->stateMachine->canComplete($order)) {
            throw new InvalidOrderStateException(sprintf('Cannot complete order in %s state', $order->getStatus()->value));
        }

        // Set end time
        $order->setEndTime(new \DateTimeImmutable());

        // Calculate final price
        $totalMinutes = $this->calculateTotalMinutes($order);
        $priceResult = $this->calculatePrice($order, $totalMinutes);

        // Update order
        $order->setActualAmount($priceResult->finalPrice);
        $this->stateMachine->transitionTo($order, OrderStatus::COMPLETED);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Dispatch events
        $this->eventDispatcher->dispatch(new BillingEndedEvent($order, $priceResult));

        // Check if refund is required
        if ($order->getPrepaidAmount() > 0 && $order->getRefundAmount() > 0) {
            $this->eventDispatcher->dispatch(new RefundRequiredEvent($order, $order->getRefundAmount()));
        }

        return [
            'order' => $order,
            'price' => $priceResult,
        ];
    }

    private function calculateTotalMinutes(DurationBillingOrder $order): int
    {
        $end = $order->getEndTime() ?? new \DateTimeImmutable();
        $start = $order->getStartTime();

        return (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    public function getCurrentPrice(int|string $orderId): PriceResult
    {
        $order = $this->orderRepository->findById($orderId);
        if (null === $order) {
            throw new OrderNotFoundException(sprintf('Order with ID %s not found', $orderId));
        }

        $currentMinutes = $this->calculateElapsedMinutes($order);

        return $this->calculatePrice($order, $currentMinutes);
    }

    public function findActiveOrders(string $userId): array
    {
        return $this->orderRepository->findActiveOrdersByUser($userId);
    }

    public function findOrderByCode(string $orderCode): ?DurationBillingOrder
    {
        return $this->orderRepository->findByOrderCode($orderCode);
    }
}
