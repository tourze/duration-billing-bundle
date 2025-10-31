<?php

namespace Tourze\DurationBillingBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\DurationBillingEvent;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

/**
 * @internal
 */
#[CoversClass(BillingEndedEvent::class)]
final class BillingEndedEventTest extends TestCase
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

    public function testBillingEndedEventWithPriceResult(): void
    {
        $priceResult = new PriceResult(
            basePrice: 50.0,
            finalPrice: 45.0,
            billableMinutes: 180,
            freeMinutes: 30,
            breakdown: ['hourly' => 50.0]
        );

        $event = new BillingEndedEvent($this->order, $priceResult);

        $this->assertInstanceOf(DurationBillingEvent::class, $event);
        $this->assertSame($this->order, $event->getOrder());
        $this->assertSame($priceResult, $event->getPriceResult());
        $this->assertSame(45.0, $event->getFinalPrice());
        $this->assertSame(180, $event->getBillableMinutes());
    }
}
