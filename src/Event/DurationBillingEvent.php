<?php

namespace Tourze\DurationBillingBundle\Event;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;

abstract class DurationBillingEvent
{
    private readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        private readonly DurationBillingOrder $order,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getOrder(): DurationBillingOrder
    {
        return $this->order;
    }

    public function getUserId(): string
    {
        return $this->order->getUserId();
    }

    public function getOrderCode(): string
    {
        return $this->order->getOrderCode();
    }

    public function getProductName(): string
    {
        return $this->order->getProduct()->getName();
    }

    public function getProduct(): DurationBillingProduct
    {
        return $this->order->getProduct();
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
