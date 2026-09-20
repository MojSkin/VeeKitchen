<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;

/**
 * The numbers behind the admin dashboard, in one place.
 *
 * Both the Inertia page and the XLSX export read this service, so the
 * workbook can never disagree with what's on screen.
 */
class DashboardSnapshotService
{
    /**
     * Everything the dashboard page and the export consume.
     *
     * @return array{salesChart: array, today: array, orderStatuses: array, lowStock: array, tables: array}
     */
    public function snapshot(Branch $branch): array
    {
        return [
            'salesChart' => $this->salesChart($branch),
            'today' => $this->todayStats($branch),
            'orderStatuses' => $this->orderStatuses($branch),
            'lowStock' => $this->lowStock($branch),
            'tables' => $this->tablesSnapshot($branch),
        ];
    }

    /**
     * The 14-day sales trend: daily revenue (Toman) and order counts.
     * Days with no sales stay on the axis at zero so the line reads true.
     *
     * @return array{days: array<int, array{date: string, label: string, revenue: int, orders: int}>, revenue_total: int, orders_total: int, best_day_label: string}
     */
    public function salesChart(Branch $branch): array
    {
        $start = now()->subDays(13)->startOfDay();

        $daily = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.branch_id', $branch->id)
            ->where('payments.paid_at', '>=', $start)
            ->get([
                'payments.amount',
                'payments.paid_at',
            ])
            ->groupBy(fn (Payment $payment) => $payment->paid_at->toDateString())
            ->map(fn ($payments, $date): array => [
                'revenue' => (int) $payments->sum('amount'),
                'orders' => $payments->count(),
            ]);

        $days = collect(range(0, 13))->map(function (int $offset) use ($daily): array {
            $date = now()->subDays(13 - $offset)->startOfDay();
            $key = $date->toDateString();
            $day = $daily->get($key, ['revenue' => 0, 'orders' => 0]);

            return [
                'date' => $key,
                'label' => $date->format('m-d'),
                'revenue' => (int) $day['revenue'],
                'orders' => (int) $day['orders'],
            ];
        })->values();

        $best = $days->sortByDesc('revenue')->first();

        return [
            'days' => $days->all(),
            'revenue_total' => $days->sum('revenue'),
            'orders_total' => $days->sum('orders'),
            'best_day_label' => $best['revenue'] > 0 ? $best['label'] : '',
        ];
    }

    /**
     * Today's headline numbers: paid revenue, order count, and the
     * average ticket (mean over paid orders).
     *
     * @return array{revenue: int, orders: int, average_ticket: int}
     */
    public function todayStats(Branch $branch): array
    {
        $since = now()->startOfDay();

        $revenue = (int) Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.branch_id', $branch->id)
            ->where('payments.paid_at', '>=', $since)
            ->sum('payments.amount');

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->where('placed_at', '>=', $since)
            ->count();

        $paidOrders = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.branch_id', $branch->id)
            ->where('payments.paid_at', '>=', $since)
            ->distinct('payments.order_id')
            ->count('payments.order_id');

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'average_ticket' => $paidOrders > 0 ? (int) round($revenue / $paidOrders) : 0,
        ];
    }

    /**
     * Count orders per status for the pipeline strip.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    public function orderStatuses(Branch $branch): array
    {
        $counts = Order::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [
                OrderStatus::AwaitingPayment,
                OrderStatus::Queued,
                OrderStatus::Preparing,
                OrderStatus::Ready,
            ])
            ->get(['status'])
            ->groupBy(fn (Order $order) => $order->status->value)
            ->map->count();

        return collect([
            OrderStatus::AwaitingPayment,
            OrderStatus::Queued,
            OrderStatus::Preparing,
            OrderStatus::Ready,
        ])->map(fn (OrderStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => $counts->get($status->value, 0),
        ])->values()->all();
    }

    /**
     * Materials at/below their alert threshold, worst first.
     *
     * @return array<int, array{id: int, name: string, unit_label: string, current: float, threshold: float}>
     */
    public function lowStock(Branch $branch): array
    {
        return InventoryItem::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->orderBy('current_stock')
            ->get()
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit_label' => $item->unit->label(),
                'current' => (float) $item->current_stock,
                'threshold' => (float) $item->low_stock_threshold,
            ])->values()->all();
    }

    /**
     * Dining-room snapshot: table counts per status.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    public function tablesSnapshot(Branch $branch): array
    {
        $counts = RestaurantTable::query()
            ->where('branch_id', $branch->id)
            ->get(['status'])
            ->groupBy(fn (RestaurantTable $table) => $table->status->value)
            ->map->count();

        return collect(TableStatus::cases())->map(fn (TableStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => $counts->get($status->value, 0),
        ])->values()->all();
    }
}
