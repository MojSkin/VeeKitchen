<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_number' => $this->resource->order_number,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'subtotal' => $this->resource->subtotal,
            'discount_total' => $this->resource->discount_total,
            'total' => $this->resource->total,
            'notes' => $this->resource->notes,
            'guest_name' => $this->resource->guest_name,
            'placed_at' => $this->resource->placed_at?->toIso8601String(),
            'paid_at' => $this->resource->paid_at?->toIso8601String(),
            'ready_at' => $this->resource->ready_at?->toIso8601String(),
            'delivered_at' => $this->resource->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->resource->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->resource->cancel_reason,
            'table' => $this->resource->table ? [
                'id' => $this->resource->table->id,
                'label' => $this->resource->table->label,
            ] : null,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
