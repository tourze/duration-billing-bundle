<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\DurationBillingBundle\Exception\InvalidPrepaidAmountException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidPrepaidAmountException::class)]
final class InvalidPrepaidAmountExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidPrepaidAmountException::class);
        $this->expectExceptionMessage('预付金额无效');

        throw new InvalidPrepaidAmountException('预付金额无效');
    }

    public function testExceptionInheritance(): void
    {
        $exception = new InvalidPrepaidAmountException();
        $this->assertInstanceOf(DurationBillingException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
