<?php

namespace Tourze\DurationBillingBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DurationBillingBundle\Contract\DurationBillingOrderRepositoryInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DurationBillingOrder>
 */
#[AsRepository(entityClass: DurationBillingOrder::class)]
class DurationBillingOrderRepository extends ServiceEntityRepository implements DurationBillingOrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DurationBillingOrder::class);
    }

    public function findById($id): ?DurationBillingOrder
    {
        return $this->find($id);
    }

    public function findByOrderCode(string $orderCode): ?DurationBillingOrder
    {
        $result = $this->createQueryBuilder('o')
            ->where('o.orderCode = :orderCode')
            ->setParameter('orderCode', $orderCode)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof DurationBillingOrder ? $result : null;
    }

    /**
     * @param OrderStatus[]|null $statuses
     * @return DurationBillingOrder[]
     */
    public function findActiveOrdersByUser(string $userId, ?array $statuses = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.userId = :userId')
            ->setParameter('userId', $userId)
        ;

        if (null === $statuses) {
            // Default active statuses
            $statuses = [OrderStatus::ACTIVE, OrderStatus::FROZEN, OrderStatus::PREPAID, OrderStatus::PENDING_PAYMENT];
        }

        if ([] !== $statuses) {
            $qb->andWhere('o.status IN (:statuses)')
                ->setParameter('statuses', $statuses)
            ;
        }

        $result = $qb->orderBy('o.startTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        /** @var mixed[] $result */
        return array_filter($result, fn ($item): bool => $item instanceof DurationBillingOrder);
    }

    public function findByBusinessReference(string $businessType, string $businessId): ?DurationBillingOrder
    {
        // Find all orders and filter in PHP for better compatibility
        $orders = $this->createQueryBuilder('o')
            ->orderBy('o.startTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        if (!is_array($orders)) {
            return null;
        }

        foreach ($orders as $order) {
            if (!$order instanceof DurationBillingOrder) {
                continue;
            }
            $metadata = $order->getMetadata();
            if (isset($metadata['business_type'], $metadata['business_id'])
                && $metadata['business_type'] === $businessType
                && $metadata['business_id'] === $businessId) {
                return $order;
            }
        }

        return null;
    }

    public function countActiveOrders(string $userId): int
    {
        $activeStatuses = [OrderStatus::ACTIVE, OrderStatus::FROZEN, OrderStatus::PREPAID, OrderStatus::PENDING_PAYMENT];

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.userId = :userId')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('userId', $userId)
            ->setParameter('statuses', $activeStatuses)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * @return DurationBillingOrder[]
     */
    public function findOrdersToEnd(\DateTimeImmutable $cutoffTime): array
    {
        // Find orders that should be automatically ended
        $result = $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.startTime <= :cutoffTime')
            ->setParameter('status', OrderStatus::ACTIVE)
            ->setParameter('cutoffTime', $cutoffTime)
            ->getQuery()
            ->getResult()
        ;

        /** @var mixed[] $result */
        return array_filter($result, fn ($item): bool => $item instanceof DurationBillingOrder);
    }

    /**
     * @return DurationBillingOrder[]
     */
    public function findExpiredFrozenOrders(int $freezeMinutes): array
    {
        // Find frozen orders that have exceeded their freeze time
        $cutoffTime = new \DateTimeImmutable(sprintf('-%d minutes', $freezeMinutes));

        $result = $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.frozenAt <= :cutoffTime')
            ->setParameter('status', OrderStatus::FROZEN)
            ->setParameter('cutoffTime', $cutoffTime)
            ->getQuery()
            ->getResult()
        ;

        /** @var mixed[] $result */
        return array_filter($result, fn ($item): bool => $item instanceof DurationBillingOrder);
    }

    public function save(DurationBillingOrder $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DurationBillingOrder $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
