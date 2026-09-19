<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\StockMovement;

/**
 * Read-only warehouse reporting. The append-only StockMovement ledger is
 * the single source of truth — reports aggregate it, never the live stock
 * columns, so history stays reproducible.
 */
class WarehouseReportService
{
    /**
     * Today's ledger movements for a branch, grouped by movement type:
     * a type-level summary plus the item lines behind each type.
     *
     * "Today" follows the application timezone — the same day boundary
     * the order counter and payment timestamps use.
     *
     * @return array{types: array<int, array{type: string, label: string, total: float, movements: int, items: array<int, array{name: string, unit_label: string, total: float}>}>, movement_count: int, generated_at: string}
     */
    public function todayByType(Branch $branch): array
    {
        $movements = StockMovement::query()
            ->whereHas('inventoryItem', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('created_at', '>=', now()->startOfDay())
            ->with('inventoryItem')
            ->orderBy('created_at')
            ->get();

        // Group by type first, then per material inside each type. Quantities
        // keep their ledger sign — consumption/waste are negative — so each
        // type's `total` shows the real net effect on the warehouse.
        $types = collect(StockMovementType::cases())
            ->map(fn (StockMovementType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'total' => 0.0,
                'movements' => 0,
                'items' => [],
            ])
            ->keyBy('type');

        foreach ($movements as $movement) {
            $type = $types[$movement->type->value];
            $amount = (float) $movement->quantity;
            $item = $movement->inventoryItem;

            $type['total'] += $amount;
            $type['movements'] += 1;

            $lineKey = $item->name;
            $items = collect($type['items']);

            $existing = $items->first(fn (array $line) => $line['name'] === $lineKey);

            if ($existing === null) {
                $items->push([
                    'name' => $item->name,
                    'unit_label' => $item->unit->label(),
                    'total' => $amount,
                ]);
            } else {
                $items->transform(fn (array $line) => $line['name'] === $lineKey
                    ? ['name' => $line['name'], 'unit_label' => $line['unit_label'], 'total' => $line['total'] + $amount]
                    : $line);
            }

            $type['items'] = $items
                ->sortByDesc(fn (array $line) => abs((float) $line['total']))
                ->values()
                ->all();

            $types[$movement->type->value] = $type;
        }

        return [
            'types' => $types->values()->all(),
            'movement_count' => $movements->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
