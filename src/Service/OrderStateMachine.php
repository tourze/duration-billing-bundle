<?php

namespace Tourze\DurationBillingBundle\Service;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;

class OrderStateMachine
{
    public function transitionTo(DurationBillingOrder $order, OrderStatus $newStatus): void
    {
        $currentStatus = $order->getStatus();

        if (!$currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidOrderStateException(sprintf('Cannot transition from %s to %s', $currentStatus->name, $newStatus->name));
        }

        $order->setStatus($newStatus);
    }

    public function canFreeze(DurationBillingOrder $order): bool
    {
        return OrderStatus::ACTIVE === $order->getStatus();
    }

    public function canResume(DurationBillingOrder $order): bool
    {
        return OrderStatus::FROZEN === $order->getStatus();
    }

    public function canComplete(DurationBillingOrder $order): bool
    {
        $status = $order->getStatus();

        return OrderStatus::ACTIVE === $status || OrderStatus::FROZEN === $status || OrderStatus::PREPAID === $status;
    }

    public function canCancel(DurationBillingOrder $order): bool
    {
        $status = $order->getStatus();

        return OrderStatus::ACTIVE === $status || OrderStatus::FROZEN === $status;
    }

    public function isActive(DurationBillingOrder $order): bool
    {
        return $order->getStatus()->isActive();
    }

    public function isTerminal(DurationBillingOrder $order): bool
    {
        return $order->getStatus()->isTerminal();
    }
}
