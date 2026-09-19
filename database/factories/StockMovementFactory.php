<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'type' => $this->faker->randomElement([
                StockMovementType::Purchase,
                StockMovementType::Consumption,
                StockMovementType::Waste,
                StockMovementType::Adjustment,
            ]),
            'quantity' => $this->faker->randomFloat(3, -5, 50),
            'order_id' => null,
            'payment_id' => null,
            'reason' => $this->faker->optional()->sentence(),
            'user_id' => null,
        ];
    }

    public function consumption(float $quantity): static
    {
        return $this->state(fn () => [
            'type' => StockMovementType::Consumption,
            'quantity' => abs($quantity) * -1,
        ]);
    }

    public function purchase(float $quantity): static
    {
        return $this->state(fn () => [
            'type' => StockMovementType::Purchase,
            'quantity' => abs($quantity),
        ]);
    }
}
