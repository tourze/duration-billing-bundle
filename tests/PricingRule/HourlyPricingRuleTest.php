<?php

namespace Tourze\DurationBillingBundle\Tests\PricingRule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;

/**
 * @internal
 */
#[CoversClass(HourlyPricingRule::class)]
final class HourlyPricingRuleTest extends TestCase
{
    public function testCalculatePriceWithUpRounding(): void
    {
        $rule = new HourlyPricingRule(10.0, RoundingMode::UP);

        $this->assertSame(10.0, $rule->calculatePrice(60));  // 1小时
        $this->assertSame(20.0, $rule->calculatePrice(61));  // 1小时1分钟，向上取整为2小时
        $this->assertSame(20.0, $rule->calculatePrice(120)); // 2小时
    }

    public function testCalculatePriceWithDownRounding(): void
    {
        $rule = new HourlyPricingRule(10.0, RoundingMode::DOWN);

        $this->assertSame(10.0, $rule->calculatePrice(60));  // 1小时
        $this->assertSame(10.0, $rule->calculatePrice(61));  // 1小时1分钟，向下取整为1小时
        $this->assertSame(10.0, $rule->calculatePrice(119)); // 1小时59分钟，向下取整为1小时
        $this->assertSame(20.0, $rule->calculatePrice(120)); // 2小时
    }

    public function testCalculatePriceWithNearestRounding(): void
    {
        $rule = new HourlyPricingRule(10.0, RoundingMode::NEAREST);

        $this->assertSame(10.0, $rule->calculatePrice(60));  // 1小时
        $this->assertSame(10.0, $rule->calculatePrice(89));  // 1小时29分钟，四舍五入为1小时
        $this->assertSame(20.0, $rule->calculatePrice(90));  // 1小时30分钟，四舍五入为2小时
        $this->assertSame(20.0, $rule->calculatePrice(120)); // 2小时
    }

    public function testGetDescription(): void
    {
        $rule = new HourlyPricingRule(10.0, RoundingMode::UP);
        $this->assertSame('10.00元/小时，up取整', $rule->getDescription());
    }

    public function testSerialize(): void
    {
        $rule = new HourlyPricingRule(10.0, RoundingMode::UP);
        $serialized = $rule->serialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('price_per_hour', $serialized);
        $this->assertArrayHasKey('rounding_mode', $serialized);
        $this->assertSame(10.0, $serialized['price_per_hour']);
        $this->assertSame('up', $serialized['rounding_mode']);
    }

    public function testDeserialize(): void
    {
        $data = [
            'price_per_hour' => 15.0,
            'rounding_mode' => 'down',
        ];

        $rule = HourlyPricingRule::deserialize($data);

        $this->assertInstanceOf(HourlyPricingRule::class, $rule);
        $this->assertSame(15.0, $rule->calculatePrice(60));
        $this->assertSame('15.00元/小时，down取整', $rule->getDescription());
    }

    public function testDeserializeWithDefaultRoundingMode(): void
    {
        $data = ['price_per_hour' => 20.0];

        $rule = HourlyPricingRule::deserialize($data);

        $this->assertSame('20.00元/小时，up取整', $rule->getDescription());
    }

    public function testValidate(): void
    {
        $validRule = new HourlyPricingRule(10.0, RoundingMode::UP);
        $this->assertTrue($validRule->validate());

        $invalidRule = new HourlyPricingRule(-10.0, RoundingMode::UP);
        $this->assertFalse($invalidRule->validate());
    }
}
