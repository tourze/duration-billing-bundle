<?php

namespace Tourze\DurationBillingBundle\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;

/**
 * @internal
 */
#[CoversClass(PricingRuleInterface::class)]
final class PricingRuleInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(PricingRuleInterface::class));
    }

    public function testInterfaceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(PricingRuleInterface::class);

        $this->assertTrue($reflection->hasMethod('calculatePrice'));
        $this->assertTrue($reflection->hasMethod('getDescription'));
        $this->assertTrue($reflection->hasMethod('serialize'));
        $this->assertTrue($reflection->hasMethod('deserialize'));
        $this->assertTrue($reflection->hasMethod('validate'));
    }
}
