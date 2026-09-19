<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Events\StockChanged;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductRecipe;
use App\Models\StockMovement;
use App\Models\User;
use RuntimeException;
use Throwable;

/**
 * Warehouse side-effects of sales.
 *
 * When an order is paid, every recipe line of every ordered product is
 * deducted from the branch's stock. Idempotency comes from the ledger
 * itself: consumption rows are unique per (payment_id, inventory_item_id),
 * so replaying a payment can never deduct twice.
 */
class InventoryService
{
    /**
     * Deduct the materials consumed by a paid order.
     *
     * MUST run inside the same transaction as markPaid(). A payment id is
     * required because it anchors the unique ledger key.
     *
     * @throws RuntimeException when stock would go negative
     */
    public function deductForOrder(Order $order, Payment $payment, ?User $actor = null): void
    {
        // Aggregate per material first: two order lines of the same product
        // must deduct once, for the summed quantity.
        $requirements = $this->requirementsFor($order);
        $actorId = $actor?->id ?? $payment->received_by;

        foreach ($requirements as $requirement) {
            $item = InventoryItem::query()
                ->whereKey($requirement['inventory_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $item->current_stock < $requirement['amount']) {
                throw new RuntimeException(
                    "موجودی «{$item->name}» برای این سفارش کافی نیست.",
                );
            }

            $deducted = $this->deduct($item, $requirement['amount'], $order, $payment, $actorId);

            if ($deducted !== null) {
                $item->current_stock = (float) $item->current_stock + $deducted;
                $item->save();
                StockChanged::dispatch($item->refresh());
            }
        }
    }

    /**
     * Return the deducted materials when a paid order is cancelled.
     *
     * The inverse consumption rows are unique per (order_id, type) through
     * the reason marker, so re-cancelling can never double-return.
     */
    public function returnForCancelledOrder(Order $order, ?User $actor = null): void
    {
        if ($order->status !== OrderStatus::Cancelled) {
            return;
        }

        $hasConsumption = $order->stockMovements()
            ->where('type', StockMovementType::Consumption)
            ->exists();

        if (! $hasConsumption) {
            return; // Nothing was ever deducted (e.g. an unpaid cancellation).
        }

        $requirements = $this->requirementsFor($order);

        if ($requirements === []) {
            return;
        }

        foreach ($requirements as $requirement) {
            $item = InventoryItem::query()
                ->whereKey($requirement['inventory_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyReturned = $order->stockMovements()
                ->where('inventory_item_id', $item->id)
                ->where('type', StockMovementType::Return)
                ->exists();

            if ($alreadyReturned) {
                continue;
            }

            $item->current_stock = (float) $item->current_stock + $requirement['amount'];
            $item->save();
            StockChanged::dispatch($item->refresh());

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'type' => StockMovementType::Return,
                'quantity' => $requirement['amount'],
                'order_id' => $order->id,
                'user_id' => $actor?->id,
                'reason' => "بازگشت متریال کنسلی سفارش شماره {$order->order_number}",
            ]);
        }
    }

    /**
     * Return the material (not) deducted for a payment — the unique
     * (payment_id, inventory_item_id) ledger key is the idempotency guard.
     */
    protected function deduct(
        InventoryItem $item,
        float $amount,
        Order $order,
        Payment $payment,
        ?int $actorId,
    ): ?float {
        try {
            return StockMovement::create([
                'inventory_item_id' => $item->id,
                'type' => StockMovementType::Consumption,
                'quantity' => -1 * $amount,
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'user_id' => $actorId,
                'reason' => "مصرف خودکار سفارش شماره {$order->order_number}",
            ])->quantity;
        } catch (Throwable $uniqueViolation) {
            // The (payment_id, inventory_item_id) pair already exists — this
            // payment was processed before. Replay is a no-op.
            return null;
        }
    }

    /**
     * All materials an order consumes, keyed and summed per item.
     *
     * @return array<int, array{inventory_item_id: int, amount: float}>
     */
    public function requirementsFor(Order $order): array
    {
        $lines = [];

        ProductRecipe::query()
            ->whereIn('product_id', $order->items->pluck('product_id'))
            ->with('inventoryItem')
            ->get()
            ->each(function (ProductRecipe $recipe) use (&$lines, $order): void {
                $orderedQuantity = (int) $order->items
                    ->where('product_id', $recipe->product_id)
                    ->sum('quantity');

                $amount = (float) $recipe->quantity_per_unit * $orderedQuantity;

                $key = $recipe->inventory_item_id;
                $lines[$key] ??= ['inventory_item_id' => $key, 'amount' => 0.0];
                $lines[$key]['amount'] += $amount;
            });

        return array_values($lines);
    }
}
