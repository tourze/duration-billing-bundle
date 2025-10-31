<?php

namespace Tourze\DurationBillingBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;
use Tourze\DurationBillingBundle\Service\OrderStateMachine;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(OrderStateMachine::class)]
#[RunTestsInSeparateProcesses]
final class OrderStateMachineTest extends AbstractIntegrationTestCase
{
    private OrderStateMachine $stateMachine;

    private DurationBillingOrder $order;

    public function testTransitionTo(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->stateMachine->transitionTo($this->order, OrderStatus::COMPLETED);

        $this->assertSame(OrderStatus::COMPLETED, $this->order->getStatus());
    }

    public function testTransitionFromActiveToCancelled(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->stateMachine->transitionTo($this->order, OrderStatus::CANCELLED);

        $this->assertSame(OrderStatus::CANCELLED, $this->order->getStatus());
    }

    public function testTransitionFromActiveToFrozen(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->stateMachine->transitionTo($this->order, OrderStatus::FROZEN);

        $this->assertSame(OrderStatus::FROZEN, $this->order->getStatus());
    }

    public function testTransitionFromActiveToCompleted(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->stateMachine->transitionTo($this->order, OrderStatus::COMPLETED);

        $this->assertSame(OrderStatus::COMPLETED, $this->order->getStatus());
    }

    public function testTransitionFromFrozenToActive(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->stateMachine->transitionTo($this->order, OrderStatus::ACTIVE);

        $this->assertSame(OrderStatus::ACTIVE, $this->order->getStatus());
    }

    public function testTransitionFromFrozenToCompleted(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->stateMachine->transitionTo($this->order, OrderStatus::COMPLETED);

        $this->assertSame(OrderStatus::COMPLETED, $this->order->getStatus());
    }

    public function testTransitionFromFrozenToCancelled(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->stateMachine->transitionTo($this->order, OrderStatus::CANCELLED);

        $this->assertSame(OrderStatus::CANCELLED, $this->order->getStatus());
    }

    public function testInvalidTransitionFromCompletedThrowsException(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Cannot transition from COMPLETED to ACTIVE');

        $this->stateMachine->transitionTo($this->order, OrderStatus::ACTIVE);
    }

    public function testInvalidTransitionFromCancelledThrowsException(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Cannot transition from CANCELLED to ACTIVE');

        $this->stateMachine->transitionTo($this->order, OrderStatus::ACTIVE);
    }

    public function testCanFreezeActiveOrder(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->assertTrue($this->stateMachine->canFreeze($this->order));
    }

    public function testCannotFreezeFrozenOrder(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->assertFalse($this->stateMachine->canFreeze($this->order));
    }

    public function testCannotFreezeCompletedOrder(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->assertFalse($this->stateMachine->canFreeze($this->order));
    }

    public function testCannotFreezeCancelledOrder(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->assertFalse($this->stateMachine->canFreeze($this->order));
    }

    public function testCanResumeFrozenOrder(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->assertTrue($this->stateMachine->canResume($this->order));
    }

    public function testCannotResumeActiveOrder(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->assertFalse($this->stateMachine->canResume($this->order));
    }

    public function testCannotResumeCompletedOrder(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->assertFalse($this->stateMachine->canResume($this->order));
    }

    public function testCannotResumeCancelledOrder(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->assertFalse($this->stateMachine->canResume($this->order));
    }

    public function testCanCompleteActiveOrder(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->assertTrue($this->stateMachine->canComplete($this->order));
    }

    public function testCanCompleteFrozenOrder(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->assertTrue($this->stateMachine->canComplete($this->order));
    }

    public function testCannotCompleteCompletedOrder(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->assertFalse($this->stateMachine->canComplete($this->order));
    }

    public function testCannotCompleteCancelledOrder(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->assertFalse($this->stateMachine->canComplete($this->order));
    }

    public function testCanCancelActiveOrder(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->assertTrue($this->stateMachine->canCancel($this->order));
    }

    public function testCanCancelFrozenOrder(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->assertTrue($this->stateMachine->canCancel($this->order));
    }

    public function testCannotCancelCompletedOrder(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->assertFalse($this->stateMachine->canCancel($this->order));
    }

    public function testCannotCancelCancelledOrder(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->assertFalse($this->stateMachine->canCancel($this->order));
    }

    public function testIsActiveReturnsTrueForActiveStatus(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->assertTrue($this->stateMachine->isActive($this->order));
    }

    public function testIsActiveReturnsFalseForFrozenStatus(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->assertFalse($this->stateMachine->isActive($this->order));
    }

    public function testIsActiveReturnsFalseForCompletedStatus(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->assertFalse($this->stateMachine->isActive($this->order));
    }

    public function testIsActiveReturnsFalseForCancelledStatus(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->assertFalse($this->stateMachine->isActive($this->order));
    }

    public function testIsTerminalReturnsTrueForCompletedStatus(): void
    {
        $this->order->setStatus(OrderStatus::COMPLETED);

        $this->assertTrue($this->stateMachine->isTerminal($this->order));
    }

    public function testIsTerminalReturnsTrueForCancelledStatus(): void
    {
        $this->order->setStatus(OrderStatus::CANCELLED);

        $this->assertTrue($this->stateMachine->isTerminal($this->order));
    }

    public function testIsTerminalReturnsFalseForActiveStatus(): void
    {
        $this->order->setStatus(OrderStatus::ACTIVE);

        $this->assertFalse($this->stateMachine->isTerminal($this->order));
    }

    public function testIsTerminalReturnsFalseForFrozenStatus(): void
    {
        $this->order->setStatus(OrderStatus::FROZEN);

        $this->assertFalse($this->stateMachine->isTerminal($this->order));
    }

    protected function onSetUp(): void
    {
        // 使用服务容器获取服务实例，避免直接实例化
        $this->stateMachine = self::getService(OrderStateMachine::class);

        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test Description');

        $this->order = new DurationBillingOrder();
        $this->order->setProduct($product);
        $this->order->setUserId('test-user-123');
        $this->order->setOrderCode('ORDER-' . uniqid());
        $this->order->setStartTime(new \DateTimeImmutable());
    }
}
