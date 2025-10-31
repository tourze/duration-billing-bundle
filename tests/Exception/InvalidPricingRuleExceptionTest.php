<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\DurationBillingBundle\Exception\InvalidPricingRuleException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidPricingRuleException::class)]
final class InvalidPricingRuleExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidPricingRuleException::class);
        $this->expectExceptionMessage('定价规则无效');

        throw new InvalidPricingRuleException('定价规则无效');
    }

    public function testExceptionInheritance(): void
    {
        $exception = new InvalidPricingRuleException();
        $this->assertInstanceOf(DurationBillingException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
