<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Queued = 'queued';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'منتظر پرداخت',
            self::Queued => 'در صف آماده‌سازی',
            self::Preparing => 'در حال آماده‌سازی',
            self::Ready => 'آماده تحویل',
            self::Delivered => 'تحویل شده',
            self::Cancelled => 'کنسل شده',
        };
    }

    /**
     * Tailwind-facing token consumed by `StatusChip.vue`.
     */
    public function tone(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'amber',
            self::Queued => 'sky',
            self::Preparing => 'violet',
            self::Ready => 'green',
            self::Delivered => 'slate',
            self::Cancelled => 'red',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Queued, self::Cancelled],
            self::Queued => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Statuses that keep an order on the kitchen display.
     *
     * @return array<int, string>
     */
    public static function kitchenQueue(): array
    {
        return [self::Queued->value, self::Preparing->value];
    }
}
