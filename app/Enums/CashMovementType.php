<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Withdrawal = 'withdrawal';
    case Deposit = 'deposit';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Withdrawal => 'برداشت نقدی',
            self::Deposit => 'واریز نقدی',
            self::Adjustment => 'اصلاح دفتری',
        };
    }

    /**
     * The signed effect on the drawer: money out is negative.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Withdrawal => -1,
            self::Deposit, self::Adjustment => 1,
        };
    }
}
