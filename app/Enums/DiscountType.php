<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
        };
    }

    /**
     * The discount amount this type yields for a base amount.
     */
    public function amountFor(int $baseToman, int $value): int
    {
        return match ($this) {
            // Floor keeps the customer's benefit honest — never round a
            // percentage discount UP to charge more.
            self::Percentage => (int) floor($baseToman * min($value, 100) / 100),
            self::Fixed => $value,
        };
    }
}
