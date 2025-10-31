<?php

namespace Tourze\DurationBillingBundle\Contract;

use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;

interface DurationBillingProductRepositoryInterface
{
    /**
     * @param int|string $id
     */
    public function findById($id): ?DurationBillingProduct;

    /**
     * @return DurationBillingProduct[]
     */
    public function findEnabledProducts(): array;

    public function findByName(string $name): ?DurationBillingProduct;
}
