<?php

namespace App\Enums;

enum MeasurementUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'گرم',
            self::Kilogram => 'کیلوگرم',
            self::Milliliter => 'میلی‌لیتر',
            self::Liter => 'لیتر',
            self::Piece => 'عدد',
        };
    }

    /**
     * Stock is held in the item's own unit; this converts a recipe amount
     * between compatible metric units (g↔kg, ml↔l) at deduction time.
     */
    public function convertFrom(self $from, float $amount): float
    {
        return $amount * $from->toGramsOrMilliliters() / $this->toGramsOrMilliliters();
    }

    protected function toGramsOrMilliliters(): float
    {
        return match ($this) {
            self::Gram, self::Milliliter => 1.0,
            self::Kilogram, self::Liter => 1000.0,
            self::Piece => 1.0,
        };
    }

    /**
     * Whether two units describe the same physical dimension.
     */
    public function isCompatibleWith(self $other): bool
    {
        return $this === $other
            || (in_array($this, [self::Gram, self::Kilogram], true) && in_array($other, [self::Gram, self::Kilogram], true))
            || (in_array($this, [self::Milliliter, self::Liter], true) && in_array($other, [self::Milliliter, self::Liter], true));
    }
}
