<?php

namespace Tourze\DurationBillingBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

/**
 * @internal
 */
#[CoversClass(PriceTier::class)]
final class PriceTierTest extends TestCase
{
    public function testPriceTierIsImmutable(): void
    {
        $tier = new PriceTier(
            fromMinutes: 0,
            toMinutes: 60,
            pricePerHour: 10.0
        );

        $this->assertEquals(0, $tier->fromMinutes);
        $this->assertEquals(60, $tier->toMinutes);
        $this->assertEquals(10.0, $tier->pricePerHour);
    }

    public function testReadonlyProperties(): void
    {
        $tier = new PriceTier(
            fromMinutes: 0,
            toMinutes: 60,
            pricePerHour: 10.0
        );

        $reflection = new \ReflectionClass($tier);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                sprintf('Property %s should be readonly', $property->getName())
            );
        }
    }

    // Basic bounded tier [0-60]
    #[TestWith([0, 60, 0, true])] // Start boundary
    #[TestWith([0, 60, 30, true])] // Middle
    #[TestWith([0, 60, 59, true])] // Just before end
    #[TestWith([0, 60, 60, false])] // End boundary (exclusive)
    #[TestWith([0, 60, 61, false])] // After end
    #[TestWith([0, 60, -1, false])] // Before start
    // Unbounded tier [60-∞]
    #[TestWith([60, null, 60, true])] // Start boundary
    #[TestWith([60, null, 120, true])] // After start
    #[TestWith([60, null, 9999, true])] // Large value
    #[TestWith([60, null, 59, false])] // Before start
    // Middle tier [30-90]
    #[TestWith([30, 90, 29, false])] // Before start
    #[TestWith([30, 90, 30, true])] // Start boundary
    #[TestWith([30, 90, 60, true])] // Middle
    #[TestWith([30, 90, 89, true])] // Just before end
    #[TestWith([30, 90, 90, false])] // End boundary (exclusive)
    public function testContains(int $fromMinutes, ?int $toMinutes, int $testMinutes, bool $expected): void
    {
        $tier = new PriceTier($fromMinutes, $toMinutes, 10.0);

        $this->assertEquals(
            $expected,
            $tier->contains($testMinutes),
            sprintf(
                'Tier [%d-%s] should%s contain %d minutes',
                $fromMinutes,
                null === $toMinutes ? '∞' : (string) $toMinutes,
                $expected ? '' : ' not',
                $testMinutes
            )
        );
    }

    // Tier [0-60]
    #[TestWith([0, 60, 30, 30])] // Total within tier
    #[TestWith([0, 60, 60, 60])] // Total equals tier end
    #[TestWith([0, 60, 90, 60])] // Total exceeds tier
    #[TestWith([0, 60, 0, 0])] // Zero total
    // Tier [60-120]
    #[TestWith([60, 120, 50, 0])] // Total before tier
    #[TestWith([60, 120, 90, 30])] // Total partially in tier
    #[TestWith([60, 120, 180, 60])] // Total exceeds tier
    // Unbounded tier [120-∞]
    #[TestWith([120, null, 100, 0])] // Total before tier
    #[TestWith([120, null, 150, 30])] // Total in tier
    #[TestWith([120, null, 300, 180])] // Large total
    // Edge cases
    #[TestWith([0, 0, 10, 0])] // Zero-width tier
    #[TestWith([10, 10, 20, 0])] // Zero-width tier
    public function testGetApplicableMinutes(
        int $fromMinutes,
        ?int $toMinutes,
        int $totalMinutes,
        int $expectedApplicableMinutes,
    ): void {
        $tier = new PriceTier($fromMinutes, $toMinutes, 10.0);

        $this->assertEquals(
            $expectedApplicableMinutes,
            $tier->getApplicableMinutes($totalMinutes),
            sprintf(
                'Tier [%d-%s] with total %d minutes should have %d applicable minutes',
                $fromMinutes,
                null === $toMinutes ? '∞' : (string) $toMinutes,
                $totalMinutes,
                $expectedApplicableMinutes
            )
        );
    }

    public function testUnboundedTier(): void
    {
        $tier = new PriceTier(120, null, 5.0);

        $this->assertNull($tier->toMinutes);
        $this->assertTrue($tier->contains(120));
        $this->assertTrue($tier->contains(9999));
        $this->assertFalse($tier->contains(119));
    }
}
