<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitCost = $this->faker->randomElement([25_000, 40_000, 60_000, 120_000]);
        $quantity = $this->faker->randomFloat(3, 1, 20);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => (int) round($unitCost * $quantity),
        ];
    }

    public function forOrder(PurchaseOrder $order): static
    {
        return $this->state(fn () => ['purchase_order_id' => $order->id]);
    }

    public function forItem(InventoryItem $item, float $quantity, int $unitCost): static
    {
        return $this->state(fn () => [
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => (int) round($unitCost * $quantity),
        ]);
    }
}
