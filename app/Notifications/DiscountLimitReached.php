<?php

namespace App\Notifications;

use App\Models\Discount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired when a discount's global usage ceiling is reached — the discount
 * will stop applying to new carts, so admins should know before guests do.
 */
class DiscountLimitReached extends Notification
{
    use Queueable;

    public function __construct(
        public Discount $discount,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'discount_id' => $this->discount->id,
            'name' => $this->discount->name,
            'code' => $this->discount->code,
            'usage_limit_total' => $this->discount->usage_limit_total,
            'title' => 'سقف استفاده از تخفیف پر شد',
            'message' => sprintf(
                'تخفیف «%s» به سقف %s بار استفاده رسید و دیگر اعمال نمی‌شود.',
                $this->discount->name,
                number_format((int) $this->discount->usage_limit_total),
            ),
        ];
    }
}
