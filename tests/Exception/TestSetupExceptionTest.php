<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\DurationBillingBundle\Exception\TestSetupException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(TestSetupException::class)]
final class TestSetupExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(TestSetupException::class);
        $this->expectExceptionMessage('测试配置错误');

        throw new TestSetupException('测试配置错误');
    }

    public function testExceptionInheritance(): void
    {
        $exception = new TestSetupException();
        $this->assertInstanceOf(DurationBillingException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
