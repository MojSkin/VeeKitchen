<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRecipe>
 */
class ProductRecipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity_per_unit' => $this->faker->randomFloat(3, 0.05, 2),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }

    public function consuming(InventoryItem $item, float $quantityPerUnit): static
    {
        return $this->state(fn () => [
            'inventory_item_id' => $item->id,
            'quantity_per_unit' => $quantityPerUnit,
        ]);
    }
}
