<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;

/**
 * Read-only warehouse reporting. The append-only StockMovement ledger is
 * the single source of truth — reports aggregate it, never the live stock
 * columns, so history stays reproducible.
 */
class WarehouseReportService
{
    /**
     * Ledger movements for a branch between two day boundaries (inclusive),
     * grouped by movement type: a type-level summary plus the item lines
     * behind each type.
     *
     * Boundaries follow the application timezone — the same day boundary
     * the order counter and payment timestamps use.
     *
     * @param  Carbon  $from  inclusive start-of-day boundary
     * @param  Carbon  $to  inclusive end-of-day boundary
     * @return array{types: array<int, array{type: string, label: string, total: float, movements: int, value: int, items: array<int, array{name: string, unit_label: string, total: float, value: int, effective_cost: int|null}>}>, movement_count: int, generated_at: string, from: string, to: string, inflow_value: int, outflow_value: int}
     */
    public function rangeByType(Branch $branch, Carbon $from, Carbon $to): array
    {
        $movements = StockMovement::query()
            ->whereHas('inventoryItem', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->where('created_at', '<=', $to->copy()->endOfDay())
            ->with('inventoryItem')
            ->orderBy('created_at')
            ->get();

        // Group by type first, then per material inside each type. Quantities
        // keep their ledger sign — consumption/waste are negative — so each
        // type's `total` shows the real net effect on the warehouse. `value`
        // fields are always positive Toman figures (|quantity| × the row's
        // historical unit cost): negative quantities are money flowing OUT
        // of the warehouse, so the UI colours them by the type's direction,
        // not by a minus sign. Rows written before cost snapshots existed
        // fall back to the material's current cost.
        $types = collect(StockMovementType::cases())
            ->map(fn (StockMovementType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'total' => 0.0,
                'movements' => 0,
                'value' => 0,
                'items' => [],
            ])
            ->keyBy('type');

        foreach ($movements as $movement) {
            $type = $types[$movement->type->value];
            $amount = (float) $movement->quantity;
            $item = $movement->inventoryItem;
            $cost = UnitCostSnapshot::valuationCost($movement);
            $value = $cost === null ? 0 : (int) round(abs($amount) * $cost);

            $type['total'] += $amount;
            $type['movements'] += 1;
            $type['value'] += $value;

            $lineKey = $item->name;
            $items = collect($type['items']);

            $existing = $items->first(fn (array $line) => $line['name'] === $lineKey);

            if ($existing === null) {
                $items->push([
                    'name' => $item->name,
                    'unit_label' => $item->unit->label(),
                    'total' => $amount,
                    'value' => $value,
                    'effective_cost' => $cost,
                ]);
            } else {
                $items->transform(fn (array $line) => $line['name'] === $lineKey
                    ? [
                        'name' => $line['name'],
                        'unit_label' => $line['unit_label'],
                        'total' => $line['total'] + $amount,
                        'value' => $line['value'] + $value,
                        // Merged lines quote the value-weighted cost, not the
                        // last-seen one: 1 kg @40k + 1 kg @60k shows 50k.
                        'effective_cost' => ($line['value'] + $value) > 0
                            ? (int) round(($line['value'] + $value) / max(abs($line['total'] + $amount), 0.001))
                            : null,
                    ]
                    : $line);
            }

            $type['items'] = $items
                ->sortByDesc(fn (array $line) => $line['value'])
                ->values()
                ->all();

            $types[$movement->type->value] = $type;
        }

        $outflowValue = collect([StockMovementType::Consumption, StockMovementType::Waste])
            ->sum(fn (StockMovementType $type) => $types[$type->value]['value']);

        $inflowValue = collect([StockMovementType::Purchase, StockMovementType::Return, StockMovementType::Adjustment])
            ->sum(fn (StockMovementType $type) => $types[$type->value]['value']);

        return [
            'types' => $types->values()->all(),
            'movement_count' => $movements->count(),
            'generated_at' => now()->toIso8601String(),
            'from' => $from->copy()->startOfDay()->toIso8601String(),
            'to' => $to->copy()->endOfDay()->toIso8601String(),
            'outflow_value' => $outflowValue,
            'inflow_value' => $inflowValue,
        ];
    }

    /**
     * Today-only convenience wrapper.
     *
     * @return array{types: array<int, array{type: string, label: string, total: float, movements: int, items: array<int, array{name: string, unit_label: string, total: float}>}>, movement_count: int, generated_at: string, from: string, to: string}
     */
    public function todayByType(Branch $branch): array
    {
        return $this->rangeByType($branch, now()->startOfDay(), now());
    }
}
