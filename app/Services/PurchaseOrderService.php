<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Events\StockChanged;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
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
     * Add one item's quantity to stock and ledger it.
     */
    protected function receiveItem(PurchaseOrder $order, PurchaseOrderItem $item, ?User $actor): void
    {
        $material = $item->inventoryItem()->lockForUpdate()->firstOrFail();

        $material->current_stock = (float) $material->current_stock + (float) $item->quantity;
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
