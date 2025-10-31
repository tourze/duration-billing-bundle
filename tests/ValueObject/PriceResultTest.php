<?php

namespace Tourze\DurationBillingBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

/**
 * @internal
 */
#[CoversClass(PriceResult::class)]
final class PriceResultTest extends TestCase
{
    public function testPriceResultIsImmutable(): void
    {
        $result = new PriceResult(
            basePrice: 100.0,
            finalPrice: 80.0,
            billableMinutes: 60,
            freeMinutes: 15,
            breakdown: ['discount' => 20.0]
        );

        $this->assertEquals(100.0, $result->basePrice);
        $this->assertEquals(80.0, $result->finalPrice);
        $this->assertEquals(60, $result->billableMinutes);
        $this->assertEquals(15, $result->freeMinutes);
        $this->assertEquals(['discount' => 20.0], $result->breakdown);
    }

    public function testReadonlyProperties(): void
    {
        $result = new PriceResult(
            basePrice: 100.0,
            finalPrice: 80.0,
            billableMinutes: 60,
            freeMinutes: 15
        );

        $reflection = new \ReflectionClass($result);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                sprintf('Property %s should be readonly', $property->getName())
            );
        }
    }

    public function testGetDiscountCalculation(): void
    {
        $result = new PriceResult(
            basePrice: 100.0,
            finalPrice: 80.0,
            billableMinutes: 60,
            freeMinutes: 15
        );

        $this->assertEquals(20.0, $result->getDiscount());
    }

    public function testHasDiscountWhenDiscountExists(): void
    {
        $result = new PriceResult(
            basePrice: 100.0,
            finalPrice: 80.0,
            billableMinutes: 60,
            freeMinutes: 15
        );

        $this->assertTrue($result->hasDiscount());
    }

    public function testHasDiscountWhenNoDiscount(): void
    {
        $result = new PriceResult(
            basePrice: 100.0,
            finalPrice: 100.0,
            billableMinutes: 60,
            freeMinutes: 0
        );

        $this->assertFalse($result->hasDiscount());
    }

    public function testNegativeDiscountScenario(): void
    {
        // When final price is higher than base price (e.g., minimum charge applied)
        $result = new PriceResult(
            basePrice: 50.0,
            finalPrice: 60.0,
            billableMinutes: 30,
            freeMinutes: 0
        );

        $this->assertEquals(-10.0, $result->getDiscount());
        $this->assertFalse($result->hasDiscount());
    }

    public function testZeroValues(): void
    {
        $result = new PriceResult(
            basePrice: 0.0,
            finalPrice: 0.0,
            billableMinutes: 0,
            freeMinutes: 60
        );

        $this->assertEquals(0.0, $result->basePrice);
        $this->assertEquals(0.0, $result->finalPrice);
        $this->assertEquals(0, $result->billableMinutes);
        $this->assertEquals(60, $result->freeMinutes);
        $this->assertEquals(0.0, $result->getDiscount());
        $this->assertFalse($result->hasDiscount());
    }
}
