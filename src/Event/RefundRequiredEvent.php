<?php

namespace Tourze\DurationBillingBundle\Event;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;

class RefundRequiredEvent extends DurationBillingEvent
{
    public function __construct(
        DurationBillingOrder $order,
        private readonly float $refundAmount,
    ) {
        parent::__construct($order);
    }

    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }
}
