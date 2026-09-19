<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A guest just placed an order — the cashier sees it instantly.
 */
class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->order->branch_id}.cashier"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'total' => $this->order->total,
            'status' => $this->order->status->value,
            'table_id' => $this->order->restaurant_table_id,
            'guest_name' => $this->order->guest_name,
            'items_count' => (int) $this->order->items()->sum('quantity'),
            'placed_at' => $this->order->placed_at?->toIso8601String(),
        ];
    }
}
