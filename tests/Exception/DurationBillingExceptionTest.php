<?php

namespace Tourze\DurationBillingBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Exception\DurationBillingException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingException::class)]
final class DurationBillingExceptionTest extends AbstractExceptionTestCase
{
    public function testDurationBillingExceptionIsAbstract(): void
    {
        $reflection = new \ReflectionClass(DurationBillingException::class);
        $this->assertTrue($reflection->isAbstract());
    }
}
