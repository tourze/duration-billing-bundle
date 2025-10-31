<?php

namespace Tourze\DurationBillingBundle\Event;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

class BillingEndedEvent extends DurationBillingEvent
{
    public function __construct(
        DurationBillingOrder $order,
        private readonly PriceResult $priceResult,
    ) {
        parent::__construct($order);
    }

    public function getPriceResult(): PriceResult
    {
        return $this->priceResult;
    }

    public function getFinalPrice(): float
    {
        return $this->priceResult->finalPrice;
    }

    public function getBillableMinutes(): int
    {
        return $this->priceResult->billableMinutes;
    }
}
