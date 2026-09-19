<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Any status transition — drives the customer's tracker and the
 * public "ready for pickup" display. The pickup payload is deliberately
 * minimal: number, table, timestamp. No customer data on a public channel.
 */
class OrderStatusChanged implements ShouldBroadcast
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
        $channels = [
            new Channel("order.{$this->order->id}"),
            new Channel("branch.{$this->order->branch_id}.pickup"),
        ];

        if ($this->order->restaurant_table_id !== null) {
            $channels[] = new Channel("table.{$this->order->restaurant_table_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'table_id' => $this->order->restaurant_table_id,
            'ready_at' => $this->order->ready_at?->toIso8601String(),
        ];
    }
}
