<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableStatus;
use App\Events\OrderPaid;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\User;
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
    ): Order {
        if ($cart === []) {
            throw new InvalidArgumentException('سبد خرید خالی است.');
        }

        if ($table !== null && $table->branch_id !== $branchId) {
            throw new InvalidArgumentException('میز به این شعبه تعلق ندارد.');
        }

        $quoted = $this->quotes->quote($branchId, $cart);
        $total = $quoted['subtotal']->roundUp();

        return DB::transaction(function () use ($branchId, $table, $customer, $quoted, $total, $guestName, $notes): Order {
            $order = new Order([
                'guest_token' => bin2hex(random_bytes(20)),
                'guest_name' => $guestName,
                'status' => OrderStatus::AwaitingPayment,
                'subtotal' => $quoted['subtotal']->toman,
                'discount_total' => 0,
                'total' => $total->toman,
                'notes' => $notes,
                'placed_at' => now(),
            ]);

            $order->branch_id = $branchId;
            $order->restaurant_table_id = $table?->id;
            $order->customer_id = $customer?->id;
            $order->save();

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

        return DB::transaction(function () use ($order, $method, $receiver): Order {
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

            $order->payments()->create([
                'received_by' => $receiver?->id ?? Auth::id(),
                'method' => $method,
                'amount' => $order->total,
                'paid_at' => $order->paid_at,
            ]);

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
