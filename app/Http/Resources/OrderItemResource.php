<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property OrderItem $resource
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_name' => $this->resource->product_name,
            'quantity' => $this->resource->quantity,
            'unit_price' => $this->resource->unit_price,
            'line_total' => $this->resource->line_total,
            'notes' => $this->resource->notes,
        ];
    }
}
