<?php

namespace App\Enums;

enum CostComponentType: string
{
    /** A flat Toman amount added to the cost. */
    case Fixed = 'fixed';

    /** Basis points of the running cost: 100 = 1%, 9_000 = 90%. */
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'مبلغ ثابت',
            self::Percent => 'درصدی',
        };
    }
}
