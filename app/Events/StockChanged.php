<?php

namespace App\Events;

use App\Models\InventoryItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A branch's stock moved (consumption, purchase, waste, adjustment).
 * The admin inventory board listens on this to keep the low-stock
 * meters live without a refresh.
 */
class StockChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public InventoryItem $item,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->item->branch_id}.inventory"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stock.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->item->id,
            'current_stock' => (float) $this->item->current_stock,
            'low_stock_threshold' => (float) $this->item->low_stock_threshold,
            'low' => $this->item->isLowStock(),
        ];
    }
}
