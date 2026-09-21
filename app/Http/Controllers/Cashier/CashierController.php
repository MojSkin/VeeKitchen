<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableStatus;
use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CashierController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected ShiftService $shifts,
    ) {}

    /**
     * The cashier screen: pending-payment orders plus the live queue.
     */
    public function index(Request $request): Response
    {
        $branchId = $this->branchId($request);

        $pending = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', OrderStatus::AwaitingPayment)
            ->with(['items', 'table'])
            ->latest('placed_at')
            ->get();

        $active = Order::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', [OrderStatus::Queued, OrderStatus::Preparing, OrderStatus::Ready])
            ->with(['items', 'table'])
            ->orderBy('paid_at')
            ->get();

        $tables = RestaurantTable::query()
            ->where('branch_id', $branchId)
            ->orderBy('label')
            ->get();

        $shift = $this->shifts->openShiftFor($request->user(), $branchId);

        return Inertia::render('Cashier/Index', [
            // resolve() unwraps the resource collection — inside Inertia
            // props a collection would otherwise serialize as {data: [...]}.
            'pendingOrders' => OrderResource::collection($pending)->resolve(),
            'activeOrders' => OrderResource::collection($active)->resolve(),
            'tables' => $tables->map(fn (RestaurantTable $table) => [
                'id' => $table->id,
                'label' => $table->label,
                'status' => $table->status->value,
                'status_label' => $table->status->label(),
            ]),
            'shift' => $shift === null ? null : [
                'id' => $shift->id,
                'opened_at' => $shift->opened_at?->toIso8601String(),
                'expected_cash' => $shift->computeExpectedCash(),
                'cash_payments' => $shift->cashPaymentsTotal(),
                'card_payments' => $shift->cardPaymentsTotal(),
            ],
        ]);
    }

    /**
     * Confirm the in-person payment; assigns the daily number.
     */
    public function pay(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:cash,card'],
        ]);

        try {
            $order = $this->orders->markPaid(
                $order,
                PaymentMethod::from($validated['method']),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            // The no-open-shift guard (and friends) surfaces as a flash,
            // not a 500 — the cashier sees what to do next.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "پرداخت سفارش شماره {$order->order_number} تایید شد.");
    }

    /**
     * The printable 80mm receipt.
     */
    public function receipt(Order $order): Response
    {
        $order->load(['items', 'table', 'payments']);

        return Inertia::render('Cashier/Receipt', [
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Repeat the TTS announcement from the cashier screen.
     */
    public function repeatAnnouncement(Order $order): RedirectResponse
    {
        // Re-broadcast the current state; the pickup display re-announces it.
        OrderPaid::dispatch($order->refresh());

        return back()->with('success', 'اعلام صوتی دوباره پخش شد.');
    }

    /**
     * Manually free a table (guests left without paying / settled).
     */
    public function releaseTable(RestaurantTable $table): RedirectResponse
    {
        $hasOpenOrders = $table->orders()
            ->whereIn('status', [OrderStatus::AwaitingPayment, OrderStatus::Queued, OrderStatus::Preparing, OrderStatus::Ready])
            ->exists();

        if ($hasOpenOrders) {
            return back()->with('error', 'این میز سفارش باز دارد؛ ابتدا سفارش‌ها را تسویه کنید.');
        }

        $table->update([
            'status' => TableStatus::Free,
            'occupied_at' => null,
        ]);

        return back()->with('success', "میز {$table->label} آزاد شد.");
    }

    /**
     * Staff without a branch (admins) fall back to the first branch.
     */
    protected function branchId(Request $request): int
    {
        $user = $request->user();

        if ($user->branch_id !== null) {
            return $user->branch_id;
        }

        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();

        abort_if($branch === null, 503, 'هیچ شعبه فعالی ثبت نشده است.');

        return $branch->id;
    }
}
