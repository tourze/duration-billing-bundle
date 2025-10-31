<?php

namespace Tourze\DurationBillingBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Event\DurationBillingEvent;
use Tourze\DurationBillingBundle\Event\FreezeExpiredEvent;

/**
 * @internal
 */
#[CoversClass(FreezeExpiredEvent::class)]
final class FreezeExpiredEventTest extends TestCase
{
    private DurationBillingOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->createMock(DurationBillingProduct::class);
        $product->method('getName')->willReturn('测试产品');

        $this->order = $this->createMock(DurationBillingOrder::class);
        $this->order->method('getProduct')->willReturn($product);
        $this->order->method('getOrderCode')->willReturn('FREEZE001');
        $this->order->method('getUserId')->willReturn('USER456');
    }

    public function testFreezeExpiredEvent(): void
    {
        $event = new FreezeExpiredEvent($this->order);

        $this->assertInstanceOf(DurationBillingEvent::class, $event);
        $this->assertSame($this->order, $event->getOrder());
        $this->assertSame('FREEZE001', $event->getOrderCode());
        $this->assertSame('USER456', $event->getUserId());
        $this->assertSame('测试产品', $event->getProductName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
}
