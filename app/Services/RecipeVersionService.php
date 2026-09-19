<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductRecipeVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Recipe history: every save of a product's recipe freezes a snapshot —
 * the lines with the unit costs of that moment, the component chain, and
 * the three price figures from CostCalculator. Versions are sequential per
 * product (1, 2, 3, ...) and immutable.
 */
class RecipeVersionService
{
    public function __construct(
        protected CostCalculator $calculator,
    ) {}

    /**
     * Record a version for the product's current recipes + cost state.
     * Call inside the same transaction that rewrote the recipes.
     */
    public function record(Product $product, ?User $actor = null): ProductRecipeVersion
    {
        return DB::transaction(function () use ($product, $actor): ProductRecipeVersion {
            $product->loadMissing('recipes.inventoryItem', 'costComponents');

            $cost = $this->calculator->forProduct($product);

            $lines = $product->recipes->map(fn ($recipe) => [
                'inventory_item_id' => $recipe->inventory_item_id,
                'name' => $recipe->inventoryItem->name,
                'unit_label' => $recipe->inventoryItem->unit->label(),
                'unit_cost' => $recipe->inventoryItem->unit_cost,
                'quantity_per_unit' => (float) $recipe->quantity_per_unit,
            ])->values()->all();

            $components = collect($cost['components'])->map(fn (array $component) => [
                'label' => $component['label'],
                'type' => $component['type'],
                'value' => $component['value'],
                'amount' => $component['amount']->toman,
                'running' => $component['running']->toman,
            ])->values()->all();

            return ProductRecipeVersion::create([
                'product_id' => $product->id,
                'created_by' => $actor?->id,
                'version_number' => $this->nextVersionNumber($product),
                'lines' => $lines,
                'components' => $components,
                'material_cost' => $cost['material_cost']->toman,
                'cost_price' => $cost['cost_price']->toman,
                'suggested_sale_price' => $cost['suggested_sale_price']->toman,
            ]);
        });
    }

    /**
     * The next sequential number for this product, inside the transaction.
     */
    protected function nextVersionNumber(Product $product): int
    {
        $max = ProductRecipeVersion::query()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->max('version_number');

        return ((int) $max) + 1;
    }
}
