<?php

namespace Tourze\DurationBillingBundle\ValueObject;

final class PriceTier
{
    public function __construct(
        public readonly int $fromMinutes,
        public readonly ?int $toMinutes,
        public readonly float $pricePerHour,
    ) {
    }

    public function getApplicableMinutes(int $totalMinutes): int
    {
        // If we haven't reached this tier yet
        if ($totalMinutes <= $this->fromMinutes) {
            return 0;
        }

        // Calculate the minutes that fall within this tier
        $startInTier = $this->fromMinutes;
        $endInTier = $this->toMinutes ?? $totalMinutes;

        // The actual minutes used in this tier
        $minutesInTier = min($totalMinutes, $endInTier) - $startInTier;

        return max(0, $minutesInTier);
    }

    public function contains(int $minutes): bool
    {
        return $minutes >= $this->fromMinutes
               && (null === $this->toMinutes || $minutes < $this->toMinutes);
    }

    public function getStartMinutes(): int
    {
        return $this->fromMinutes;
    }

    public function getEndMinutes(): ?int
    {
        return $this->toMinutes;
    }

    public function getPricePerHour(): float
    {
        return $this->pricePerHour;
    }
}
