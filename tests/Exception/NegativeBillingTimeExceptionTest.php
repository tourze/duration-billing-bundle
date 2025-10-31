<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\DurationBillingBundle\Exception\NegativeBillingTimeException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(NegativeBillingTimeException::class)]
final class NegativeBillingTimeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(NegativeBillingTimeException::class);
        $this->expectExceptionMessage('计费时间不能为负数');

        throw new NegativeBillingTimeException('计费时间不能为负数');
    }

    public function testExceptionInheritance(): void
    {
        $exception = new NegativeBillingTimeException();
        $this->assertInstanceOf(DurationBillingException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
