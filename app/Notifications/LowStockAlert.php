<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired when a material's stock crosses down to/below its alert threshold.
 * Database channel only for now — mail/push can join the via() later.
 */
class LowStockAlert extends Notification
{
    use Queueable;

    public function __construct(
        public InventoryItem $item,
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
            'inventory_item_id' => $this->item->id,
            'name' => $this->item->name,
            'current_stock' => (float) $this->item->current_stock,
            'low_stock_threshold' => (float) $this->item->low_stock_threshold,
            'unit' => $this->item->unit->value,
            'title' => 'هشدار موجودی کم',
            'message' => sprintf(
                'موجودی «%s» به %s %s رسید و از خط هشدار گذشت.',
                $this->item->name,
                $this->item->current_stock,
                $this->item->unit->label(),
            ),
        ];
    }
}
