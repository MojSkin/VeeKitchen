<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableStatus;
use App\Events\OrderPaid;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\Contracts\CashShiftGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The only gateway for order state changes in VeeKitchen.
 *
 * Controllers never mutate an Order directly — they call this service so the
 * rounding rule, daily numbering, and status machine stay in one place.
 */
class OrderService
{
    public function __construct(
        protected QuoteService $quotes,
        protected InventoryService $inventory,
        protected DiscountService $discounts,
        protected CashShiftGuard $shiftGuard,
    ) {}

    /**
     * Place a new order from a guest or a signed-in customer.
     *
     * @param  array<int, array{product_id: int, quantity: int, notes?: string|null}>  $cart
     */
    public function place(
        int $branchId,
        ?RestaurantTable $table,
        ?User $customer,
        array $cart,
        ?string $guestName = null,
        ?string $notes = null,
        ?string $discountCode = null,
    ): Order {
        if ($cart === []) {
            throw new InvalidArgumentException('سبد خرید خالی است.');
        }

        if ($table !== null && $table->branch_id !== $branchId) {
            throw new InvalidArgumentException('میز به این شعبه تعلق ندارد.');
        }

        $quoted = $this->quotes->quote($branchId, $cart);
        $applied = $this->discounts->bestFor(
            $branchId,
            $quoted['lines'],
            $quoted['subtotal'],
            $discountCode,
            $customer?->id,
        );

        $total = $quoted['subtotal']->minus($applied['amount'])->roundUp();

        return DB::transaction(function () use ($branchId, $table, $customer, $quoted, $applied, $total, $guestName, $notes): Order {
            $order = new Order([
                'guest_token' => bin2hex(random_bytes(20)),
                'guest_name' => $guestName,
                'status' => OrderStatus::AwaitingPayment,
                'subtotal' => $quoted['subtotal']->toman,
                'discount_total' => $applied['amount']->toman,
                'total' => $total->toman,
                'notes' => $notes,
                'placed_at' => now(),
            ]);

            $order->branch_id = $branchId;
            $order->restaurant_table_id = $table?->id;
            $order->customer_id = $customer?->id;
            $order->discount_id = $applied['discount']?->id;
            $order->save();

            if ($applied['discount'] instanceof Discount) {
                $this->discounts->recordUsage($applied['discount'], $customer?->id);
            }

            foreach ($quoted['lines'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'unit_price' => $line['unit_price']->toman,
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total']->toman,
                    'notes' => $line['notes'],
                ]);
            }

            if ($table !== null && $table->status === TableStatus::Free) {
                $table->update([
                    'status' => TableStatus::Ordering,
                    'occupied_at' => now(),
                ]);
            }

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Confirm in-person payment: assign the daily order number, record the
     * payment, and release the order into the kitchen queue.
     */
    public function markPaid(Order $order, PaymentMethod $method, ?User $receiver = null): Order
    {
        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw new RuntimeException('این سفارش در وضعیت انتظار پرداخت نیست.');
        }

        // Resolved before the transaction — the session state is not
        // available inside the closure.
        $receiver ??= User::query()->find(Auth::id());

        // Phase 3 golden rule — money only moves inside an open shift. The
        // guard resolves (and stamps) the shift the payment belongs to.
        // Headless payments (no acting user, e.g. console flows) settle
        // without a shift stamp; the cashier flow always carries a user.
        $shift = $receiver === null
            ? null
            : $this->shiftGuard->requireOpenShift($receiver, $order->branch_id);

        return DB::transaction(function () use ($order, $method, $receiver, $shift): Order {
            // Lock the branch's pending rows so two cashiers can never draw
            // the same daily number at the same moment.
            $order = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::AwaitingPayment) {
                throw new RuntimeException('این سفارش در وضعیت انتظار پرداخت نیست.');
            }

            $order->order_number = $this->nextDailyNumber($order->branch_id, $order->created_at->format('Y-m-d'));

            $order->status = OrderStatus::Queued;
            $order->paid_at = now();
            $order->save();

            $payment = $order->payments()->create([
                'received_by' => $receiver?->id,
                'method' => $method,
                'amount' => $order->total,
                'paid_at' => $order->paid_at,
            ]);

            // The audit link: every payment hangs on its shift.
            $payment->shift_id = $shift?->id;
            $payment->save();

            // Phase 2 — deduct recipe materials from the warehouse ledger.
            // Runs inside the same transaction; a stock shortfall rolls the
            // whole payment back.
            $order->loadMissing('items');
            $this->inventory->deductForOrder($order, $payment, $receiver);

            OrderPaid::dispatch($order);

            return $order->refresh();
        });
    }

    /**
     * Move an order along the status machine (or cancel it with a reason).
     */
    public function transition(Order $order, OrderStatus $target, ?User $actor = null, ?string $reason = null): Order
    {
        $current = $order->status;

        if (! $current->canTransitionTo($target)) {
            throw new RuntimeException(
                "تغییر وضعیت از «{$current->label()}» به «{$target->label()}» مجاز نیست.",
            );
        }

        if ($target === OrderStatus::Cancelled) {
            if ($reason === null || trim($reason) === '') {
                throw new InvalidArgumentException('ثبت دلیل کنسل کردن الزامی است.');
            }

            $order->cancelled_at = now();
            $order->cancel_reason = $reason;
            $order->cancelled_by = $actor?->id ?? Auth::id();
        }

        $order->status = $target;

        $order->ready_at = match ($target) {
            OrderStatus::Ready => now(),
            default => $order->ready_at,
        };

        $order->delivered_at = match ($target) {
            OrderStatus::Delivered => now(),
            default => $order->delivered_at,
        };

        $order->save();

        if ($target === OrderStatus::Cancelled) {
            $this->inventory->returnForCancelledOrder($order, $actor);
            $this->freeTableIfIdle($order);
        }

        OrderStatusChanged::dispatch($order);

        return $order->refresh();
    }

    /**
     * Free the table when nothing is left open on it.
     */
    protected function freeTableIfIdle(Order $order): void
    {
        $table = $order->table;

        if ($table === null) {
            return;
        }

        $hasOpenOrders = $table->orders()
            ->whereKeyNot($order->getKey())
            ->whereIn('status', [
                OrderStatus::AwaitingPayment,
                OrderStatus::Queued,
                OrderStatus::Preparing,
                OrderStatus::Ready,
            ])
            ->exists();

        if (! $hasOpenOrders) {
            $table->update([
                'status' => TableStatus::Free,
                'occupied_at' => null,
            ]);
        }
    }

    /**
     * The next sequential number for this branch and calendar day.
     *
     * Must be called inside a transaction holding the row lock.
     */
    protected function nextDailyNumber(int $branchId, string $day): int
    {
        $max = Order::query()
            ->where('branch_id', $branchId)
            ->whereDate('paid_at', $day)
            ->max('order_number');

        return ((int) $max) + 1;
    }
}
