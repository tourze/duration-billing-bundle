<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidOrderStateException::class)]
final class InvalidOrderStateExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('订单状态无效');

        throw new InvalidOrderStateException('订单状态无效');
    }

    public function testExceptionInheritance(): void
    {
        $exception = new InvalidOrderStateException();
        $this->assertInstanceOf(DurationBillingException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
