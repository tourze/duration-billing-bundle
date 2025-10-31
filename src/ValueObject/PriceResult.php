<?php

namespace Tourze\DurationBillingBundle\ValueObject;

final class PriceResult
{
    /**
     * @param array<string, mixed> $breakdown
     */
    public function __construct(
        public readonly float $basePrice,
        public readonly float $finalPrice,
        public readonly int $billableMinutes,
        public readonly int $freeMinutes,
        public readonly array $breakdown = [],
    ) {
    }

    public function hasDiscount(): bool
    {
        return $this->getDiscount() > 0;
    }

    public function getDiscount(): float
    {
        return $this->basePrice - $this->finalPrice;
    }
}
