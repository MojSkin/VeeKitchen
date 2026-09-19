<?php

namespace App\Services;

use App\Enums\CostComponentType;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Support\Money;

/**
 * Cost pricing per §3.3 of the requirements.
 *
 * - Material cost  = Σ (recipe quantity per unit × material unit cost)
 * - Sale price     = material cost + every cost component applied in
 *                    `position` order (fixed Toman, or percent as basis
 *                    points of the running amount)
 * - Both figures go through the 100-Toman ceiling (§5) exactly once, at
 *   the end — rounding intermediates would inflate the chain.
 *
 * `costPrice` is the production cost (materials + components flagged
 * `is_cost`, e.g. packaging or overhead), while `suggestedSalePrice`
 * additionally carries the pricing components (management profit, tax, ...).
 */
class CostCalculator
{
    /**
     * Full cost breakdown for one unit of a product.
     *
     * @return array{material_cost: Money, cost_price: Money, suggested_sale_price: Money, components: array<int, array{label: string, type: string, value: int, amount: Money, running: Money}>}
     */
    public function forProduct(Product $product): array
    {
        $materialCost = $this->materialCost($product);

        $components = [];
        $running = $materialCost;
        $costPrice = $materialCost;

        foreach ($product->costComponents()->orderBy('position')->get() as $component) {
            $amount = match ($component->type) {
                CostComponentType::Fixed => Money::of($component->value),
                CostComponentType::Percent => $running->percentage($component->percent()),
            };

            $running = $running->plus($amount);

            if ($component->is_cost) {
                $costPrice = $costPrice->plus($amount);
            }

            $components[] = [
                'label' => $component->label,
                'type' => $component->type->value,
                'value' => $component->value,
                'amount' => $amount,
                'running' => $running,
            ];
        }

        return [
            'material_cost' => $materialCost->roundUp(),
            'cost_price' => $costPrice->roundUp(),
            'suggested_sale_price' => $running->roundUp(),
            'components' => $components,
        ];
    }

    /**
     * Raw material cost for one unit, from the recipe.
     */
    public function materialCost(Product $product): Money
    {
        $cost = Money::zero();

        $product->recipes()->with('inventoryItem')->get()->each(function ($recipe) use (&$cost): void {
            /** @var InventoryItem $item */
            $item = $recipe->inventoryItem;

            $cost = $cost->plus(
                Money::of((float) $recipe->quantity_per_unit * $item->unit_cost),
            );
        });

        return $cost;
    }
}
