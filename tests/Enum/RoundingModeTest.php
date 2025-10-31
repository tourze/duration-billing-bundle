<?php

namespace Tourze\DurationBillingBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(RoundingMode::class)]
final class RoundingModeTest extends AbstractEnumTestCase
{
    public function testRoundingModeValues(): void
    {
        $this->assertEquals('up', RoundingMode::UP->value);
        $this->assertEquals('down', RoundingMode::DOWN->value);
        $this->assertEquals('nearest', RoundingMode::NEAREST->value);
    }

    public function testAllRoundingModesExist(): void
    {
        $modes = RoundingMode::cases();
        $this->assertCount(3, $modes);

        $values = array_map(fn ($mode) => $mode->value, $modes);
        $this->assertContains('up', $values);
        $this->assertContains('down', $values);
        $this->assertContains('nearest', $values);
    }

    public function testToArray(): void
    {
        $array = RoundingMode::UP->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);

        $this->assertEquals('up', $array['value']);
        $this->assertEquals('向上取整', $array['label']);
    }
}
