<?php

namespace Tourze\DurationBillingBundle\Contract;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Enum\OrderStatus;

interface DurationBillingOrderRepositoryInterface
{
    /**
     * @param int|string $id
     */
    public function findById($id): ?DurationBillingOrder;

    public function findByOrderCode(string $orderCode): ?DurationBillingOrder;

    /**
     * @param OrderStatus[] $statuses
     * @return DurationBillingOrder[]
     */
    public function findActiveOrdersByUser(string $userId, ?array $statuses = null): array;

    public function findByBusinessReference(string $businessType, string $businessId): ?DurationBillingOrder;

    public function countActiveOrders(string $userId): int;
}
