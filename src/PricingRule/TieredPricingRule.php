<?php

namespace Tourze\DurationBillingBundle\PricingRule;

use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

class TieredPricingRule implements PricingRuleInterface
{
    /**
     * @param PriceTier[] $tiers
     */
    public function __construct(
        private array $tiers,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function deserialize(array $data): self
    {
        $tiersData = $data['tiers'] ?? null;
        if (!is_array($tiersData)) {
            throw new \InvalidArgumentException('tiers must be an array');
        }

        $tiers = [];
        foreach ($tiersData as $tierData) {
            if (!is_array($tierData)) {
                throw new \InvalidArgumentException('Each tier must be an array');
            }

            $startMinutes = $tierData['start_minutes'] ?? null;
            $endMinutes = $tierData['end_minutes'] ?? null;
            $pricePerHour = $tierData['price_per_hour'] ?? null;

            if (!is_int($startMinutes)) {
                throw new \InvalidArgumentException('start_minutes must be integer');
            }
            if (null !== $endMinutes && !is_int($endMinutes)) {
                throw new \InvalidArgumentException('end_minutes must be integer or null');
            }
            if (!is_numeric($pricePerHour)) {
                throw new \InvalidArgumentException('price_per_hour must be numeric');
            }

            $tiers[] = new PriceTier(
                $startMinutes,
                $endMinutes,
                (float) $pricePerHour
            );
        }

        return new self($tiers);
    }

    public function calculatePrice(int $minutes): float
    {
        if ($minutes <= 0) {
            return 0.0;
        }

        $totalPrice = 0.0;

        foreach ($this->tiers as $tier) {
            $tierMinutes = $tier->getApplicableMinutes($minutes);
            if ($tierMinutes > 0) {
                $tierPrice = ($tierMinutes / 60) * $tier->getPricePerHour();
                $totalPrice += $tierPrice;
            }
        }

        return $totalPrice;
    }

    public function getDescription(): string
    {
        $tierCount = count($this->tiers);

        return sprintf('阶梯计费，共%d个层级', $tierCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        $tiersData = [];
        foreach ($this->tiers as $tier) {
            $tiersData[] = [
                'start_minutes' => $tier->getStartMinutes(),
                'end_minutes' => $tier->getEndMinutes(),
                'price_per_hour' => $tier->getPricePerHour(),
            ];
        }

        return [
            'tiers' => $tiersData,
        ];
    }

    public function validate(): bool
    {
        if ([] === $this->tiers) {
            return false;
        }

        if (!$this->validatePrices()) {
            return false;
        }

        $sortedTiers = $this->getSortedTiers();

        return $this->validateTierSequence($sortedTiers);
    }

    private function validatePrices(): bool
    {
        foreach ($this->tiers as $tier) {
            if ($tier->getPricePerHour() < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, PriceTier>
     */
    private function getSortedTiers(): array
    {
        $sortedTiers = $this->tiers;
        usort($sortedTiers, fn (PriceTier $a, PriceTier $b) => $a->getStartMinutes() <=> $b->getStartMinutes());

        return $sortedTiers;
    }

    /**
     * @param array<int, PriceTier> $sortedTiers
     */
    private function validateTierSequence(array $sortedTiers): bool
    {
        if (0 !== $sortedTiers[0]->getStartMinutes()) {
            return false;
        }

        if (!$this->validateTierContinuity($sortedTiers)) {
            return false;
        }

        return $this->validateLastTierIsInfinite($sortedTiers);
    }

    /**
     * @param array<int, PriceTier> $sortedTiers
     */
    private function validateTierContinuity(array $sortedTiers): bool
    {
        for ($i = 0; $i < count($sortedTiers) - 1; ++$i) {
            $currentTier = $sortedTiers[$i];
            $nextTier = $sortedTiers[$i + 1];

            if (null === $currentTier->getEndMinutes()) {
                return false;
            }

            if ($currentTier->getEndMinutes() !== $nextTier->getStartMinutes()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, PriceTier> $sortedTiers
     */
    private function validateLastTierIsInfinite(array $sortedTiers): bool
    {
        $lastTier = $sortedTiers[count($sortedTiers) - 1];

        return null === $lastTier->getEndMinutes();
    }
}
