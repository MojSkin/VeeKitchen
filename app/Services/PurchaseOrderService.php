<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Events\StockChanged;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The purchase pipeline: submit a draft to the supplier, then receive it —
 * receiving is the moment stock actually rises, one ledger row per item,
 * guarded against double-receiving.
 */
class PurchaseOrderService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * Create a draft purchase order with its item lines.
     *
     * Each line's unit cost defaults to the material's last purchase cost
     * unless the buyer overrides it; totals are filled immediately so the
     * draft board shows a meaningful figure before submission.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: float|int|string, unit_cost?: int|string|null}>  $lines
     */
    public function create(Branch $branch, Supplier $supplier, array $lines, ?User $actor = null, ?string $notes = null): PurchaseOrder
    {
        if (! $supplier->is_active) {
            throw new InvalidArgumentException("تامین‌کننده «{$supplier->name}» غیرفعال است.");
        }

        return DB::transaction(function () use ($branch, $supplier, $lines, $actor, $notes): PurchaseOrder {
            $order = PurchaseOrder::create([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'created_by' => $actor?->id,
                'status' => PurchaseOrderStatus::Draft,
                'total' => 0,
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $material = InventoryItem::query()->whereKey($line['inventory_item_id'])->firstOrFail();
                $unitCost = (int) (($line['unit_cost'] ?? null) !== null ? $line['unit_cost'] : $material->unit_cost);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'inventory_item_id' => $material->id,
                    'quantity' => (float) $line['quantity'],
                    'unit_cost' => $unitCost,
                    'line_total' => (int) round((float) $line['quantity'] * $unitCost),
                ]);
            }

            $order->recalculateTotal();

            return $order->refresh();
        });
    }

    /**
     * Send a draft order to the supplier.
     */
    public function submit(PurchaseOrder $order, ?User $actor = null): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw new RuntimeException('فقط پیش‌نویس قابل ثبت نزد تامین‌کننده است.');
        }

        if ($order->items()->count() === 0) {
            throw new InvalidArgumentException('ثبت سفارش خرید بدون قلم ممکن نیست.');
        }

        $order->recalculateTotal();
        $order->status = PurchaseOrderStatus::Ordered;
        $order->ordered_at = now();
        $order->save();

        return $order->refresh();
    }

    /**
     * Receive a submitted order: raise stock, write purchase ledger rows,
     * and stamp the receive metadata.
     *
     * Idempotent by status: a Received order can never be received again.
     */
    public function receive(PurchaseOrder $order, ?User $actor = null): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Ordered) {
            throw new RuntimeException('فقط سفارش ثبت‌شده قابل دریافت است.');
        }

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== PurchaseOrderStatus::Ordered) {
                throw new RuntimeException('فقط سفارش ثبت‌شده قابل دریافت است.');
            }

            foreach ($order->items as $item) {
                $this->receiveItem($order, $item, $actor);
            }

            $order->status = PurchaseOrderStatus::Received;
            $order->received_at = now();
            $order->received_by = $actor?->id ?? $order->received_by;
            $order->save();

            return $order->refresh();
        });
    }

    /**
     * Add one item's quantity to stock and ledger it. The item's unit cost
     * is refreshed to this PO's per-unit price — it feeds cost pricing.
     */
    protected function receiveItem(PurchaseOrder $order, PurchaseOrderItem $item, ?User $actor): void
    {
        $material = $item->inventoryItem()->lockForUpdate()->firstOrFail();

        $material->current_stock = (float) $material->current_stock + (float) $item->quantity;

        if ($item->unit_cost > 0) {
            $material->unit_cost = $item->unit_cost;
        }

        $material->save();

        StockMovement::create([
            'inventory_item_id' => $material->id,
            'type' => 'purchase',
            'quantity' => (float) $item->quantity,
            'user_id' => $actor?->id ?? $order->received_by,
            'reason' => "دریافت سفارش خرید شماره {$order->id} از {$order->supplier->name}",
        ]);

        // Receiving can rescue a low-stock item — re-evaluate the alert side.
        StockChanged::dispatch($material->refresh());
        $this->inventory->evaluateLowStock($material);
    }

    /**
     * Cancel a draft or submitted order. Received orders are final.
     */
    public function cancel(PurchaseOrder $order, ?User $actor = null, ?string $reason = null): PurchaseOrder
    {
        $cancellable = in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true);

        if (! $cancellable) {
            throw new RuntimeException('سفارش دریافت‌شده قابل کنسل شدن نیست.');
        }

        $order->status = PurchaseOrderStatus::Cancelled;
        $order->save();

        return $order->refresh();
    }
}
