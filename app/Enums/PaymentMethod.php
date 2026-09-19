<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';

    /**
     * `Online` joins this enum when a gateway is wired up; `Payment::gateway_reference`
     * already exists for it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدی',
            self::Card => 'کارت‌خوان',
        };
    }
}
