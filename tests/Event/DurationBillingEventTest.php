<?php

namespace Tourze\DurationBillingBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Event\BillingStartedEvent;
use Tourze\DurationBillingBundle\Event\DurationBillingEvent;

/**
 * @internal
 */
#[CoversClass(DurationBillingEvent::class)]
final class DurationBillingEventTest extends TestCase
{
    private DurationBillingOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->createMock(DurationBillingProduct::class);

        $this->order = $this->createMock(DurationBillingOrder::class);
        $this->order->method('getProduct')->willReturn($product);
        $this->order->method('getOrderCode')->willReturn('TEST001');
        $this->order->method('getUserId')->willReturn('USER123');
    }

    public function testBaseEventProperties(): void
    {
        // 使用具体的子类来测试抽象类的功能
        $event = new BillingStartedEvent($this->order);

        $this->assertInstanceOf(DurationBillingEvent::class, $event);
        $this->assertSame($this->order, $event->getOrder());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testEventTimeImmutability(): void
    {
        $event1 = new BillingStartedEvent($this->order);
        sleep(1);
        $event2 = new BillingStartedEvent($this->order);

        $this->assertNotEquals($event1->getOccurredAt(), $event2->getOccurredAt());
    }
}
