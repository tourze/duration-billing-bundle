<?php

namespace Tourze\DurationBillingBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum RoundingMode: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case UP = 'up';
    case DOWN = 'down';
    case NEAREST = 'nearest';

    public function getLabel(): string
    {
        return match ($this) {
            self::UP => '向上取整',
            self::DOWN => '向下取整',
            self::NEAREST => '四舍五入',
        };
    }
}
