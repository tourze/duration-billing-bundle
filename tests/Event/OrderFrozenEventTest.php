<?php

namespace Tourze\DurationBillingBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Event\DurationBillingEvent;
use Tourze\DurationBillingBundle\Event\OrderFrozenEvent;

/**
 * @internal
 */
#[CoversClass(OrderFrozenEvent::class)]
final class OrderFrozenEventTest extends TestCase
{
    private DurationBillingOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->createMock(DurationBillingProduct::class);
        $product->method('getName')->willReturn('冻结产品');

        $this->order = $this->createMock(DurationBillingOrder::class);
        $this->order->method('getProduct')->willReturn($product);
        $this->order->method('getOrderCode')->willReturn('FROZEN001');
        $this->order->method('getUserId')->willReturn('USER789');
    }

    public function testOrderFrozenEvent(): void
    {
        $event = new OrderFrozenEvent($this->order);

        $this->assertInstanceOf(DurationBillingEvent::class, $event);
        $this->assertSame($this->order, $event->getOrder());
        $this->assertSame('FROZEN001', $event->getOrderCode());
        $this->assertSame('USER789', $event->getUserId());
        $this->assertSame('冻结产品', $event->getProductName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
}
