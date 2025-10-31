<?php

namespace Tourze\DurationBillingBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum OrderStatus: string implements Itemable, Labelable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case ACTIVE = 'active';
    case FROZEN = 'frozen';
    case PREPAID = 'prepaid';
    case PENDING_PAYMENT = 'pending_payment';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return match ($this) {
            self::ACTIVE => in_array($newStatus, [self::FROZEN, self::COMPLETED, self::CANCELLED], true),
            self::FROZEN => in_array($newStatus, [self::ACTIVE, self::COMPLETED, self::CANCELLED], true),
            self::PREPAID => in_array($newStatus, [self::COMPLETED, self::PENDING_PAYMENT], true),
            self::PENDING_PAYMENT => self::COMPLETED === $newStatus,
            self::COMPLETED, self::CANCELLED => false,
        };
    }

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => '进行中',
            self::FROZEN => '暂停中',
            self::PREPAID => '预付费',
            self::PENDING_PAYMENT => '待付费',
            self::COMPLETED => '已完成',
            self::CANCELLED => '已取消',
        };
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::ACTIVE => BadgeInterface::SUCCESS,
            self::FROZEN => BadgeInterface::WARNING,
            self::PREPAID => BadgeInterface::INFO,
            self::PENDING_PAYMENT => BadgeInterface::WARNING,
            self::COMPLETED => BadgeInterface::PRIMARY,
            self::CANCELLED => BadgeInterface::DANGER,
        };
    }
}
