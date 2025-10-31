<?php

namespace Tourze\DurationBillingBundle\Service;

use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

class PriceCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function getPriceDetails(
        DurationBillingProduct $product,
        PricingRuleInterface $pricingRule,
        int $totalMinutes,
    ): array {
        $result = $this->calculate($product, $pricingRule, $totalMinutes);

        return [
            'total_minutes' => $totalMinutes,
            'free_minutes' => $result->freeMinutes,
            'billed_minutes' => $result->billableMinutes,
            'base_price' => $result->basePrice,
            'final_price' => $result->finalPrice,
            'discount' => $result->getDiscount(),
            'adjustment_reason' => $result->breakdown['adjustment_reason'] ?? null,
            'pricing_rule_description' => $pricingRule->getDescription(),
            'product_constraints' => [
                'free_minutes' => $product->getFreeMinutes(),
                'min_amount' => $product->getMinAmount(),
                'max_amount' => $product->getMaxAmount(),
            ],
        ];
    }

    public function calculate(
        DurationBillingProduct $product,
        PricingRuleInterface $pricingRule,
        int $totalMinutes,
    ): PriceResult {
        // Handle zero minutes
        if ($totalMinutes <= 0) {
            return new PriceResult(
                basePrice: 0.0,
                finalPrice: 0.0,
                billableMinutes: 0,
                freeMinutes: 0,
                breakdown: []
            );
        }

        // Calculate billable minutes after free minutes
        $freeMinutes = $product->getFreeMinutes();
        $billedMinutes = max(0, $totalMinutes - $freeMinutes);

        // If all minutes are free
        if (0 === $billedMinutes) {
            return new PriceResult(
                basePrice: 0.0,
                finalPrice: 0.0,
                billableMinutes: 0,
                freeMinutes: $freeMinutes,
                breakdown: ['adjustment_reason' => '免费时长']
            );
        }

        // Calculate base price using pricing rule
        $basePrice = $pricingRule->calculatePrice($billedMinutes);
        $finalPrice = $basePrice;
        $breakdown = [];

        // Apply minimum amount constraint
        $minAmount = $product->getMinAmount();
        if (null !== $minAmount && $finalPrice < $minAmount) {
            $finalPrice = $minAmount;
            $breakdown['adjustment_reason'] = '最低消费';
        }

        // Apply maximum amount constraint
        $maxAmount = $product->getMaxAmount();
        if (null !== $maxAmount && $finalPrice > $maxAmount) {
            $finalPrice = $maxAmount;
            $breakdown['adjustment_reason'] = '最高限价';
        }

        return new PriceResult(
            basePrice: $basePrice,
            finalPrice: $finalPrice,
            billableMinutes: $billedMinutes,
            freeMinutes: $freeMinutes,
            breakdown: $breakdown
        );
    }
}
