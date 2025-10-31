<?php

namespace Tourze\DurationBillingBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
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
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Service\DurationBillingService;
use Tourze\DurationBillingBundle\Service\OrderStateMachine;
use Tourze\DurationBillingBundle\Service\PriceCalculator;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

/**
 * @internal
 */
#[CoversClass(DurationBillingService::class)]
final class DurationBillingServiceTest extends TestCase
{
    private DurationBillingService $service;

    private DurationBillingProductRepositoryInterface $productRepository;

    private DurationBillingOrderRepositoryInterface $orderRepository;

    private OrderStateMachine $stateMachine;

    private PriceCalculator $priceCalculator;

    private EventDispatcherInterface $eventDispatcher;

    public function testStartBillingCreatesOrder(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($product)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new Callback(function ($event) {
                return $event instanceof BillingStartedEvent
                    && 'user123' === $event->getUserId();
            }))
        ;

        $order = $this->service->startBilling(123, 'user123');

        $this->assertInstanceOf(DurationBillingOrder::class, $order);
        $this->assertSame(OrderStatus::ACTIVE, $order->getStatus());
    }

    public function testStartBillingWithPrepaidAmount(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($product)
        ;

        $order = $this->service->startBilling(123, 'user123', ['prepaid_amount' => 50.0]);

        $this->assertSame(OrderStatus::PREPAID, $order->getStatus());
        $this->assertSame(50.0, $order->getPrepaidAmount());
    }

    public function testStartBillingThrowsExceptionWhenProductNotFound(): void
    {
        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(ProductNotFoundException::class);

        $this->service->startBilling(999, 'user123');
    }

    public function testFreezeBilling(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('user123');
        $order->setOrderCode('ORDER123');
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setStartTime(new \DateTimeImmutable('-1 hour'));

        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($order)
        ;

        $this->stateMachine->expects($this->once())
            ->method('canFreeze')
            ->with($order)
            ->willReturn(true)
        ;

        $this->stateMachine->expects($this->once())
            ->method('transitionTo')
            ->with($order, OrderStatus::FROZEN)
            ->willReturnCallback(function (DurationBillingOrder $order, OrderStatus $status): void {
                $order->setStatus($status);
            })
        ;

        $priceResult = new PriceResult(
            basePrice: 10.0,
            finalPrice: 10.0,
            billableMinutes: 60,
            freeMinutes: 0,
            breakdown: []
        );

        $this->priceCalculator->expects($this->once())
            ->method('calculate')
            ->willReturn($priceResult)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new Callback(function ($event) use ($order) {
                return $event instanceof OrderFrozenEvent
                    && $event->getOrder() === $order;
            }))
        ;

        $updatedOrder = $this->service->freezeBilling(123);

        $this->assertSame(OrderStatus::FROZEN, $updatedOrder->getStatus());
        $this->assertSame(10.0, $updatedOrder->getActualAmount());
    }

    public function testFreezeBillingThrowsExceptionWhenOrderNotFound(): void
    {
        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(OrderNotFoundException::class);

        $this->service->freezeBilling(999);
    }

    public function testFreezeBillingThrowsExceptionWhenInvalidState(): void
    {
        $order = new DurationBillingOrder();
        $order->setStatus(OrderStatus::COMPLETED);

        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($order)
        ;

        $this->stateMachine->expects($this->once())
            ->method('canFreeze')
            ->with($order)
            ->willReturn(false)
        ;

        $this->expectException(InvalidOrderStateException::class);

        $this->service->freezeBilling(123);
    }

    public function testResumeBilling(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('user123');
        $order->setOrderCode('ORDER123');
        $order->setStatus(OrderStatus::FROZEN);
        $order->setFrozenMinutes(30);
        $order->setStartTime(new \DateTimeImmutable('-30 minutes'));

        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($order)
        ;

        $this->stateMachine->expects($this->once())
            ->method('canResume')
            ->with($order)
            ->willReturn(true)
        ;

        $this->stateMachine->expects($this->once())
            ->method('transitionTo')
            ->with($order, OrderStatus::ACTIVE)
            ->willReturnCallback(function (DurationBillingOrder $order, OrderStatus $status): void {
                $order->setStatus($status);
            })
        ;

        $updatedOrder = $this->service->resumeBilling(123);

        // The order object is modified in place
        $this->assertSame($order, $updatedOrder);
        $this->assertGreaterThan(30, $updatedOrder->getFrozenMinutes());
    }

    public function testEndBilling(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('user123');
        $order->setOrderCode('ORDER123');
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setStartTime(new \DateTimeImmutable('-2 hours'));

        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($order)
        ;

        $this->stateMachine->expects($this->once())
            ->method('canComplete')
            ->with($order)
            ->willReturn(true)
        ;

        $this->stateMachine->expects($this->once())
            ->method('transitionTo')
            ->with($order, OrderStatus::COMPLETED)
            ->willReturnCallback(function (DurationBillingOrder $order, OrderStatus $status): void {
                $order->setStatus($status);
            })
        ;

        $priceResult = new PriceResult(
            basePrice: 20.0,
            finalPrice: 20.0,
            billableMinutes: 120,
            freeMinutes: 0,
            breakdown: []
        );

        $this->priceCalculator->expects($this->once())
            ->method('calculate')
            ->willReturn($priceResult)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new Callback(function ($event) use ($order, $priceResult) {
                return $event instanceof BillingEndedEvent
                    && $event->getOrder() === $order
                    && $event->getPriceResult() === $priceResult;
            }))
        ;

        $result = $this->service->endBilling(123);

        $this->assertSame($order, $result['order']);
        $this->assertSame($priceResult, $result['price']);
        $this->assertSame(OrderStatus::COMPLETED, $order->getStatus());
        $this->assertNotNull($order->getEndTime());
        $this->assertSame(20.0, $order->getActualAmount());
    }

    public function testGetCurrentPrice(): void
    {
        $product = new DurationBillingProduct();
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);
        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setStartTime(new \DateTimeImmutable('-90 minutes'));

        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($order)
        ;

        $priceResult = new PriceResult(
            basePrice: 15.0,
            finalPrice: 15.0,
            billableMinutes: 90,
            freeMinutes: 0,
            breakdown: []
        );

        $this->priceCalculator->expects($this->once())
            ->method('calculate')
            ->willReturn($priceResult)
        ;

        $result = $this->service->getCurrentPrice(123);

        $this->assertSame($priceResult, $result);
    }

    public function testFindActiveOrders(): void
    {
        $orders = [
            new DurationBillingOrder(),
            new DurationBillingOrder(),
        ];

        $this->orderRepository->expects($this->once())
            ->method('findActiveOrdersByUser')
            ->with('user123')
            ->willReturn($orders)
        ;

        $result = $this->service->findActiveOrders('user123');

        $this->assertCount(2, $result);
    }

    public function testFindOrderByCode(): void
    {
        $order = new DurationBillingOrder();

        $this->orderRepository->expects($this->once())
            ->method('findByOrderCode')
            ->with('ORDER123')
            ->willReturn($order)
        ;

        $result = $this->service->findOrderByCode('ORDER123');

        $this->assertSame($order, $result);
    }

    public function testEndBillingWithRefundRequired(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('user123');
        $order->setOrderCode('ORDER123');
        $order->setStatus(OrderStatus::PREPAID);
        $order->setStartTime(new \DateTimeImmutable('-30 minutes'));
        $order->setPrepaidAmount(50.0); // 预付50元

        $this->orderRepository->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($order)
        ;

        $this->stateMachine->expects($this->once())
            ->method('canComplete')
            ->with($order)
            ->willReturn(true)
        ;

        $this->stateMachine->expects($this->once())
            ->method('transitionTo')
            ->with($order, OrderStatus::COMPLETED)
            ->willReturnCallback(function (DurationBillingOrder $order, OrderStatus $status): void {
                $order->setStatus($status);
            })
        ;

        $priceResult = new PriceResult(
            basePrice: 5.0,
            finalPrice: 5.0,
            billableMinutes: 30,
            freeMinutes: 0,
            breakdown: []
        );

        $this->priceCalculator->expects($this->once())
            ->method('calculate')
            ->willReturn($priceResult)
        ;

        $eventCount = 0;
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$eventCount, $order, $priceResult) {
                ++$eventCount;
                if (1 === $eventCount) {
                    // 第一个事件应该是 BillingEndedEvent
                    $this->assertInstanceOf(BillingEndedEvent::class, $event);
                    $this->assertSame($order, $event->getOrder());
                    $this->assertSame($priceResult, $event->getPriceResult());
                } else {
                    // 第二个事件应该是 RefundRequiredEvent
                    $this->assertInstanceOf(RefundRequiredEvent::class, $event);
                    $this->assertSame($order, $event->getOrder());
                    $this->assertSame(45.0, $event->getRefundAmount()); // 50 - 5 = 45
                }

                return $event; // Return the event object
            })
        ;

        $result = $this->service->endBilling(123);

        $this->assertSame($order, $result['order']);
        $this->assertSame(5.0, $order->getActualAmount());
    }

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(DurationBillingProductRepositoryInterface::class);
        $this->orderRepository = $this->createMock(DurationBillingOrderRepositoryInterface::class);
        /**
         * 使用具体类 OrderStateMachine 创建 Mock 的理由：
         * 1. OrderStateMachine 没有对应的接口定义
         * 2. 该类是服务层的内部实现，不需要对外暴露接口
         * 3. 在测试中我们只需要 mock 其行为，不需要实际的状态转换逻辑
         * 替代方案：如果未来需要更灵活的状态机实现，可以考虑创建 StateMachineInterface
         */
        $this->stateMachine = $this->createMock(OrderStateMachine::class);
        /**
         * 使用具体类 PriceCalculator 创建 Mock 的理由：
         * 1. PriceCalculator 没有对应的接口定义
         * 2. 该类是服务层的内部实现，专门用于价格计算
         * 3. 在测试中我们需要控制价格计算的返回值，而不是执行实际计算
         * 替代方案：如果未来有多种价格计算策略，可以考虑创建 PriceCalculatorInterface
         */
        $this->priceCalculator = $this->createMock(PriceCalculator::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // 直接实例化服务，传递所有依赖的 mock
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new DurationBillingService(
            $this->productRepository,
            $this->orderRepository,
            $this->stateMachine,
            $this->priceCalculator,
            $this->eventDispatcher,
            $entityManager
        );
    }
}
