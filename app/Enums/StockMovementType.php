<?php

namespace App\Enums;

enum StockMovementType: string
{
    /** Material received from a supplier (phase 2.5+). */
    case Purchase = 'purchase';

    /** Auto-deducted when an order is paid. */
    case Consumption = 'consumption';

    /** Expired/damaged stock. */
    case Waste = 'waste';

    /** Manual count correction. */
    case Adjustment = 'adjustment';

    /** Returned to stock after a cancellation/refund. */
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'خرید',
            self::Consumption => 'مصرف',
            self::Waste => 'ضایعات',
            self::Adjustment => 'اصلاح موجودی',
            self::Return => 'بازگشت به انبار',
        };
    }
}
