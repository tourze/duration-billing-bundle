<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\DurationBillingBundle\Exception\OrderAlreadyEndedException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(OrderAlreadyEndedException::class)]
final class OrderAlreadyEndedExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(OrderAlreadyEndedException::class);
        $this->expectExceptionMessage('订单已结束');

        throw new OrderAlreadyEndedException('订单已结束');
    }

    public function testExceptionInheritance(): void
    {
        $exception = new OrderAlreadyEndedException();
        $this->assertInstanceOf(DurationBillingException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
