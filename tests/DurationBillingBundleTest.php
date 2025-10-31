<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\DurationBillingBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingBundle::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingBundleTest extends AbstractBundleTestCase
{
}
