<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductRecipeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRecipeVersion>
 */
class ProductRecipeVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'created_by' => User::factory()->admin(),
            'version_number' => 1,
            'lines' => [
                ['inventory_item_id' => 1, 'name' => 'آرد گندم', 'unit_cost' => 60_000, 'quantity_per_unit' => 0.4],
            ],
            'components' => null,
            'material_cost' => 24_000,
            'cost_price' => 24_000,
            'suggested_sale_price' => 24_000,
        ];
    }

    public function forProduct(Product $product, int $versionNumber = 1): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
            'version_number' => $versionNumber,
        ]);
    }
}
