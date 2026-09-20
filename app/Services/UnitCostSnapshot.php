<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockMovement;

/**
 * Historical unit-cost snapshots for the movement ledger.
 *
 * Every ledger row is stamped with the Toman price it was valued at, so a
 * report re-run years later reproduces the same rial values instead of
 * silently re-valuing history with today's prices. Purchase rows carry the
 * price that was actually paid; every other row falls back to the
 * material's current recorded cost at write time — the best knowledge
 * available for that moment.
 */
class UnitCostSnapshot
{
    public const SOURCE_PURCHASE = 'purchase_price';

    public const SOURCE_CURRENT = 'current_cost';

    /**
     * The cost attributes for a movement about to be created.
     *
     * @return array{unit_cost_at: int|null, unit_cost_source: string|null}
     */
    public static function forMovement(InventoryItem $item, string $type, ?int $purchaseCost = null): array
    {
        if ($type === 'purchase') {
            return [
                'unit_cost_at' => $purchaseCost ?? (int) $item->unit_cost,
                'unit_cost_source' => self::SOURCE_PURCHASE,
            ];
        }

        $current = (int) $item->unit_cost;

        if ($current <= 0) {
            return ['unit_cost_at' => null, 'unit_cost_source' => null];
        }

        return ['unit_cost_at' => $current, 'unit_cost_source' => self::SOURCE_CURRENT];
    }

    /**
     * The effective valuation cost of a ledger row: its snapshot when one
     * exists, otherwise the material's current cost (pre-snapshot rows and
     * un-costed materials alike).
     */
    public static function valuationCost(StockMovement $movement): ?int
    {
        if ($movement->unit_cost_at !== null) {
            return $movement->unit_cost_at;
        }

        return (int) $movement->inventoryItem->unit_cost ?: null;
    }
}
