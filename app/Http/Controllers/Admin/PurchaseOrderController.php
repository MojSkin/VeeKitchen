<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrders,
    ) {}

    /**
     * The procurement board: every purchase order and where it stands.
     */
    public function index(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        $orders = PurchaseOrder::query()
            ->where('branch_id', $branch->id)
            ->with(['supplier', 'items.inventoryItem'])
            ->latest('id')
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'supplier_name' => $order->supplier->name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total' => $order->total,
                'notes' => $order->notes,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'received_at' => $order->received_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->inventoryItem->name,
                    'quantity' => (float) $item->quantity,
                    'unit_label' => $item->inventoryItem->unit->label(),
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $item->line_total,
                ])->values(),
            ]);

        return Inertia::render('Admin/PurchaseOrders', [
            'orders' => $orders,
            'statuses' => collect(PurchaseOrderStatus::cases())
                ->map(fn (PurchaseOrderStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ]),
        ]);
    }

    /**
     * The new-order form: active suppliers + purchasable materials with
     * their units and last purchase costs.
     */
    public function create(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        return Inertia::render('Admin/PurchaseOrderCreate', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Supplier $supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ]),
            'items' => InventoryItem::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit_label' => $item->unit->label(),
                    'unit_cost' => $item->unit_cost,
                ]),
        ]);
    }

    /**
     * Store a new draft purchase order with its lines.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'integer', 'min:0'],
        ]);

        $lines = collect($validated['lines']);

        if ($lines->pluck('inventory_item_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'هر متریال فقط یک‌بار در سفارش می‌آید؛ برای اصلاح مقدار همان خط را ویرایش کنید.',
            ]);
        }

        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $supplier = Supplier::query()->findOrFail((int) $validated['supplier_id']);

        try {
            $order = $this->purchaseOrders->create(
                $branch,
                $supplier,
                $validated['lines'],
                $request->user(),
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()
                ->withErrors(['supplier_id' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.purchase-orders')
            ->with('success', "پیش‌نویس سفارش خرید برای «{$supplier->name}» ساخته شد.");
    }

    /**
     * Send a draft to the supplier.
     */
    public function submit(Request $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->purchaseOrders->submit($order, $request->user());
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "سفارش خرید شماره {$order->id} نزد {$order->supplier->name} ثبت شد.");
    }

    /**
     * Receive an ordered purchase order — stock rises.
     */
    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->purchaseOrders->receive($order, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "سفارش خرید شماره {$order->id} دریافت و موجودی انبار به‌روز شد.");
    }

    /**
     * Cancel a draft or submitted order.
     */
    public function cancel(Request $request, PurchaseOrder $order): RedirectResponse
    {
        try {
            $this->purchaseOrders->cancel($order, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "سفارش خرید شماره {$order->id} کنسل شد.");
    }
}
