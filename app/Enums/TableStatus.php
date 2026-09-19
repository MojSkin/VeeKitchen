<?php

namespace App\Enums;

enum TableStatus: string
{
    case Free = 'free';
    case Reserved = 'reserved';
    case Ordering = 'ordering';
    case AwaitingSettlement = 'awaiting_settlement';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'آزاد',
            self::Reserved => 'رزرو شده',
            self::Ordering => 'در حال سفارش',
            self::AwaitingSettlement => 'منتظر تسویه',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Free => 'green',
            self::Reserved => 'sky',
            self::Ordering => 'amber',
            self::AwaitingSettlement => 'violet',
        };
    }
}
