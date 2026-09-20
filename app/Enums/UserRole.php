<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Customer = 'customer';

    /**
     * Adding `Waiter` later is a new case here plus a label — no structural change.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدیر',
            self::Cashier => 'صندوق‌دار',
            self::Kitchen => 'آشپزخانه',
            self::Customer => 'مشتری',
        };
    }

    /**
     * The route a member of staff lands on after signing in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Cashier => 'cashier.index',
            self::Kitchen => 'kitchen.index',
            self::Customer => 'welcome',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function staff(): array
    {
        return [self::Admin, self::Cashier, self::Kitchen];
    }
}
