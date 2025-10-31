<?php

namespace Tourze\DurationBillingBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Event\DurationBillingEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;

/**
 * @internal
 */
#[CoversClass(RefundRequiredEvent::class)]
final class RefundRequiredEventTest extends TestCase
{
    private DurationBillingOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->createMock(DurationBillingProduct::class);
        $product->method('getName')->willReturn('退款产品');

        $this->order = $this->createMock(DurationBillingOrder::class);
        $this->order->method('getProduct')->willReturn($product);
        $this->order->method('getOrderCode')->willReturn('REFUND001');
        $this->order->method('getUserId')->willReturn('USER123');
    }

    public function testRefundRequiredEvent(): void
    {
        $refundAmount = 50.0;
        $event = new RefundRequiredEvent($this->order, $refundAmount);

        $this->assertInstanceOf(DurationBillingEvent::class, $event);
        $this->assertSame($this->order, $event->getOrder());
        $this->assertSame('REFUND001', $event->getOrderCode());
        $this->assertSame('USER123', $event->getUserId());
        $this->assertSame('退款产品', $event->getProductName());
        $this->assertSame($refundAmount, $event->getRefundAmount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testRefundAmountAccessor(): void
    {
        $refundAmount = 123.45;
        $event = new RefundRequiredEvent($this->order, $refundAmount);

        $this->assertSame($refundAmount, $event->getRefundAmount());
    }
}
