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
#[CoversClass(BillingStartedEvent::class)]
final class BillingStartedEventTest extends TestCase
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

    public function testBillingStartedEvent(): void
    {
        $event = new BillingStartedEvent($this->order);

        $this->assertInstanceOf(DurationBillingEvent::class, $event);
        $this->assertSame($this->order, $event->getOrder());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
}
