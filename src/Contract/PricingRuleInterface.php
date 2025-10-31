<?php

namespace Tourze\DurationBillingBundle\Contract;

interface PricingRuleInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public static function deserialize(array $data): self;

    public function calculatePrice(int $minutes): float;

    public function getDescription(): string;

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array;

    public function validate(): bool;
}
