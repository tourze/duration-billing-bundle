<?php

namespace Tourze\DurationBillingBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(OrderStatus::class)]
final class OrderStatusTest extends AbstractEnumTestCase
{
    // From ACTIVE
    #[TestWith([OrderStatus::ACTIVE, OrderStatus::FROZEN])]
    #[TestWith([OrderStatus::ACTIVE, OrderStatus::COMPLETED])]
    #[TestWith([OrderStatus::ACTIVE, OrderStatus::CANCELLED])]
    // From FROZEN
    #[TestWith([OrderStatus::FROZEN, OrderStatus::ACTIVE])]
    #[TestWith([OrderStatus::FROZEN, OrderStatus::COMPLETED])]
    #[TestWith([OrderStatus::FROZEN, OrderStatus::CANCELLED])]
    // From PREPAID
    #[TestWith([OrderStatus::PREPAID, OrderStatus::COMPLETED])]
    #[TestWith([OrderStatus::PREPAID, OrderStatus::PENDING_PAYMENT])]
    // From PENDING_PAYMENT
    #[TestWith([OrderStatus::PENDING_PAYMENT, OrderStatus::COMPLETED])]
    public function testValidStateTransitions(OrderStatus $fromStatus, OrderStatus $toStatus): void
    {
        $this->assertTrue(
            $fromStatus->canTransitionTo($toStatus),
            sprintf('Should be able to transition from %s to %s', $fromStatus->value, $toStatus->value)
        );
    }

    // From ACTIVE
    #[TestWith([OrderStatus::ACTIVE, OrderStatus::PENDING_PAYMENT])]
    #[TestWith([OrderStatus::ACTIVE, OrderStatus::PREPAID])]
    // From FROZEN
    #[TestWith([OrderStatus::FROZEN, OrderStatus::PENDING_PAYMENT])]
    #[TestWith([OrderStatus::FROZEN, OrderStatus::PREPAID])]
    // From PREPAID
    #[TestWith([OrderStatus::PREPAID, OrderStatus::ACTIVE])]
    #[TestWith([OrderStatus::PREPAID, OrderStatus::FROZEN])]
    #[TestWith([OrderStatus::PREPAID, OrderStatus::CANCELLED])]
    // From PENDING_PAYMENT
    #[TestWith([OrderStatus::PENDING_PAYMENT, OrderStatus::ACTIVE])]
    #[TestWith([OrderStatus::PENDING_PAYMENT, OrderStatus::FROZEN])]
    #[TestWith([OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED])]
    // From terminal states (COMPLETED and CANCELLED)
    #[TestWith([OrderStatus::COMPLETED, OrderStatus::ACTIVE])]
    #[TestWith([OrderStatus::COMPLETED, OrderStatus::FROZEN])]
    #[TestWith([OrderStatus::COMPLETED, OrderStatus::CANCELLED])]
    #[TestWith([OrderStatus::CANCELLED, OrderStatus::ACTIVE])]
    #[TestWith([OrderStatus::CANCELLED, OrderStatus::COMPLETED])]
    public function testInvalidStateTransitions(OrderStatus $fromStatus, OrderStatus $toStatus): void
    {
        $this->assertFalse(
            $fromStatus->canTransitionTo($toStatus),
            sprintf('Should not be able to transition from %s to %s', $fromStatus->value, $toStatus->value)
        );
    }

    public function testIsTerminalStatus(): void
    {
        $this->assertTrue(OrderStatus::COMPLETED->isTerminal());
        $this->assertTrue(OrderStatus::CANCELLED->isTerminal());

        $this->assertFalse(OrderStatus::ACTIVE->isTerminal());
        $this->assertFalse(OrderStatus::FROZEN->isTerminal());
        $this->assertFalse(OrderStatus::PREPAID->isTerminal());
        $this->assertFalse(OrderStatus::PENDING_PAYMENT->isTerminal());
    }

    public function testIsActiveStatus(): void
    {
        $this->assertTrue(OrderStatus::ACTIVE->isActive());

        $this->assertFalse(OrderStatus::FROZEN->isActive());
        $this->assertFalse(OrderStatus::PREPAID->isActive());
        $this->assertFalse(OrderStatus::PENDING_PAYMENT->isActive());
        $this->assertFalse(OrderStatus::COMPLETED->isActive());
        $this->assertFalse(OrderStatus::CANCELLED->isActive());
    }

    public function testCanTransitionTo(): void
    {
        $this->assertTrue(OrderStatus::ACTIVE->canTransitionTo(OrderStatus::FROZEN));
        $this->assertTrue(OrderStatus::ACTIVE->canTransitionTo(OrderStatus::COMPLETED));
        $this->assertTrue(OrderStatus::ACTIVE->canTransitionTo(OrderStatus::CANCELLED));

        $this->assertFalse(OrderStatus::ACTIVE->canTransitionTo(OrderStatus::PREPAID));
        $this->assertFalse(OrderStatus::ACTIVE->canTransitionTo(OrderStatus::PENDING_PAYMENT));
    }

    public function testToArray(): void
    {
        $array = OrderStatus::ACTIVE->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);

        $this->assertEquals('active', $array['value']);
        $this->assertEquals('进行中', $array['label']);
    }
}
