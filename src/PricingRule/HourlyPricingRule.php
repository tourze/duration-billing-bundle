<?php

namespace Tourze\DurationBillingBundle\PricingRule;

use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\Enum\RoundingMode;

class HourlyPricingRule implements PricingRuleInterface
{
    public function __construct(
        private float $pricePerHour,
        private RoundingMode $roundingMode = RoundingMode::UP,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function deserialize(array $data): self
    {
        $pricePerHour = $data['price_per_hour'] ?? null;
        if (!is_numeric($pricePerHour)) {
            throw new \InvalidArgumentException('price_per_hour must be numeric');
        }

        $roundingModeValue = $data['rounding_mode'] ?? 'up';
        if (!is_string($roundingModeValue) && !is_int($roundingModeValue)) {
            throw new \InvalidArgumentException('rounding_mode must be string or int');
        }

        return new self(
            (float) $pricePerHour,
            RoundingMode::from($roundingModeValue)
        );
    }

    public function calculatePrice(int $minutes): float
    {
        $hours = $minutes / 60;

        $roundedHours = match ($this->roundingMode) {
            RoundingMode::UP => ceil($hours),
            RoundingMode::DOWN => floor($hours),
            RoundingMode::NEAREST => round($hours),
        };

        return $roundedHours * $this->pricePerHour;
    }

    public function getDescription(): string
    {
        return sprintf('%.2f元/小时，%s取整', $this->pricePerHour, $this->roundingMode->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'price_per_hour' => $this->pricePerHour,
            'rounding_mode' => $this->roundingMode->value,
        ];
    }

    public function validate(): bool
    {
        return $this->pricePerHour >= 0;
    }
}
