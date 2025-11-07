<?php

namespace Tourze\DurationBillingBundle\Tests\PricingRule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

/**
 * @internal
 */
#[CoversClass(TieredPricingRule::class)]
final class TieredPricingRuleTest extends TestCase
{
    public function testSingleTierCalculation(): void
    {
        $tiers = [
            new PriceTier(0, null, 10.0), // 所有时间都是10元/小时
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertSame(10.0, $rule->calculatePrice(60));   // 1小时
        $this->assertSame(20.0, $rule->calculatePrice(120));  // 2小时
        $this->assertSame(50.0, $rule->calculatePrice(300));  // 5小时
    }

    public function testMultipleTierCalculation(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),      // 0-60分钟：10元/小时
            new PriceTier(60, 180, 8.0),     // 60-180分钟：8元/小时
            new PriceTier(180, null, 6.0),    // 180分钟以上：6元/小时
        ];

        $rule = new TieredPricingRule($tiers);

        // 30分钟：30/60 * 10 = 5元
        $this->assertSame(5.0, $rule->calculatePrice(30));

        // 60分钟：60/60 * 10 = 10元
        $this->assertSame(10.0, $rule->calculatePrice(60));

        // 120分钟：第一层60分钟10元 + 第二层60分钟8元 = 18元
        $this->assertSame(18.0, $rule->calculatePrice(120));

        // 240分钟：第一层60分钟10元 + 第二层120分钟16元 + 第三层60分钟6元 = 32元
        $this->assertSame(32.0, $rule->calculatePrice(240));
    }

    public function testComplexTierCalculation(): void
    {
        $tiers = [
            new PriceTier(0, 30, 0.0),       // 前30分钟免费
            new PriceTier(30, 120, 15.0),    // 30-120分钟：15元/小时
            new PriceTier(120, 240, 12.0),   // 120-240分钟：12元/小时
            new PriceTier(240, null, 10.0),   // 240分钟以上：10元/小时
        ];

        $rule = new TieredPricingRule($tiers);

        // 20分钟：免费
        $this->assertSame(0.0, $rule->calculatePrice(20));

        // 30分钟：免费
        $this->assertSame(0.0, $rule->calculatePrice(30));

        // 60分钟：前30分钟免费 + 后30分钟7.5元 = 7.5元
        $this->assertSame(7.5, $rule->calculatePrice(60));

        // 180分钟：前30分钟免费 + 90分钟22.5元 + 60分钟12元 = 34.5元
        $this->assertSame(34.5, $rule->calculatePrice(180));

        // 360分钟：前30分钟免费 + 90分钟22.5元 + 120分钟24元 + 120分钟20元 = 66.5元
        $this->assertSame(66.5, $rule->calculatePrice(360));
    }

    public function testZeroMinutesCalculation(): void
    {
        $tiers = [
            new PriceTier(0, null, 10.0),
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertSame(0.0, $rule->calculatePrice(0));
    }

    public function testCalculatePrice(): void
    {
        $tiers = [
            new PriceTier(0, 60, 15.0),      // 0-60分钟：15元/小时
            new PriceTier(60, 120, 12.0),    // 60-120分钟：12元/小时
            new PriceTier(120, null, 10.0),   // 120分钟以上：10元/小时
        ];

        $rule = new TieredPricingRule($tiers);

        // 测试各种时长的计费
        $this->assertSame(0.0, $rule->calculatePrice(0));      // 0分钟
        $this->assertSame(7.5, $rule->calculatePrice(30));     // 30分钟：30/60 * 15 = 7.5元
        $this->assertSame(15.0, $rule->calculatePrice(60));    // 60分钟：60/60 * 15 = 15元
        $this->assertSame(27.0, $rule->calculatePrice(120));   // 120分钟：60*15/60 + 60*12/60 = 15 + 12 = 27元
        $this->assertSame(37.0, $rule->calculatePrice(180));   // 180分钟：60*15/60 + 60*12/60 + 60*10/60 = 15 + 12 + 10 = 37元
    }

    public function testGetDescription(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),
            new PriceTier(60, 180, 8.0),
            new PriceTier(180, null, 6.0),
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertStringContainsString('阶梯计费', $rule->getDescription());
        $this->assertStringContainsString('3个层级', $rule->getDescription());
    }

    public function testSerialize(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),
            new PriceTier(60, null, 8.0),
        ];

        $rule = new TieredPricingRule($tiers);
        $serialized = $rule->serialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('tiers', $serialized);

        /** @var array{tiers: list<array<string, mixed>>} $serialized */
        $tiers = $serialized['tiers'];
        $this->assertCount(2, $tiers);

        $this->assertIsArray($tiers[0]);
        $this->assertEquals([
            'start_minutes' => 0,
            'end_minutes' => 60,
            'price_per_hour' => 10.0,
        ], $tiers[0]);

        $this->assertIsArray($tiers[1]);
        $this->assertEquals([
            'start_minutes' => 60,
            'end_minutes' => null,
            'price_per_hour' => 8.0,
        ], $tiers[1]);
    }

    public function testDeserialize(): void
    {
        $data = [
            'tiers' => [
                [
                    'start_minutes' => 0,
                    'end_minutes' => 60,
                    'price_per_hour' => 10.0,
                ],
                [
                    'start_minutes' => 60,
                    'end_minutes' => null,
                    'price_per_hour' => 8.0,
                ],
            ],
        ];

        $rule = TieredPricingRule::deserialize($data);

        $this->assertInstanceOf(TieredPricingRule::class, $rule);
        $this->assertSame(10.0, $rule->calculatePrice(60));
        $this->assertSame(14.0, $rule->calculatePrice(90)); // 60分钟10元 + 30分钟4元
    }

    public function testValidateWithValidTiers(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),
            new PriceTier(60, null, 8.0),
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertTrue($rule->validate());
    }

    public function testValidateWithEmptyTiers(): void
    {
        $rule = new TieredPricingRule([]);

        $this->assertFalse($rule->validate());
    }

    public function testValidateWithNegativePrice(): void
    {
        $tiers = [
            new PriceTier(0, 60, -10.0),
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertFalse($rule->validate());
    }

    public function testValidateWithOverlappingTiers(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),
            new PriceTier(30, 90, 8.0), // 重叠
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertFalse($rule->validate());
    }

    public function testValidateWithGapInTiers(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),
            new PriceTier(90, null, 8.0), // 有间隙
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertFalse($rule->validate());
    }

    public function testValidateWithoutInfiniteTier(): void
    {
        $tiers = [
            new PriceTier(0, 60, 10.0),
            new PriceTier(60, 120, 8.0), // 没有无限层级
        ];

        $rule = new TieredPricingRule($tiers);

        $this->assertFalse($rule->validate());
    }
}
