<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\StaffShift;
use Illuminate\Support\Carbon;

/**
 * The end-of-day pipeline: every branch shift that touches today — open,
 * closed today, or spanning midnight — with the money numbers the
 * settlement review needs.
 *
 * The dashboard KPI row and the EOD page consume this service, so the
 * two can never disagree.
 */
class EndOfDayService
{
    /**
     * Today's pipeline: shifts plus the day's order KPIs.
     *
     * @return array{shifts: array<int, array<string, mixed>>, open_shifts: int, closed_shifts: int, total_discrepancy: int, orders: array<string, int>}
     */
    public function pipeline(Branch $branch, ?Carbon $day = null): array
    {
        $day ??= now();

        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        // A shift belongs to today when it opened today, closed today,
        // or is still open but was opened before today ended (spans
        // midnight). Opened-ordered: newest first for the board.
        $shifts = StaffShift::query()
            ->where('branch_id', $branch->id)
            ->where(function ($query) use ($from, $to): void {
                $query
                    ->whereBetween('opened_at', [$from, $to])
                    ->orWhereBetween('closed_at', [$from, $to])
                    ->orWhere(function ($openQuery) use ($to): void {
                        $openQuery->whereNull('closed_at')->where('opened_at', '<=', $to);
                    });
            })
            ->with(['user', 'closer'])
            ->orderByDesc('opened_at')
            ->get();

        $openCount = $shifts->filter(fn (StaffShift $shift) => $shift->isOpen())->count();

        return [
            'shifts' => $shifts->map(fn (StaffShift $shift): array => $this->presentShift($shift))->all(),
            'open_shifts' => $openCount,
            'closed_shifts' => $shifts->count() - $openCount,
            'total_discrepancy' => $shifts->sum('discrepancy'),
            'orders' => $this->orderKpis($branch, $from, $to),
        ];
    }

    /**
     * The compact shift KPI row for the dashboard: today's open shifts,
     * closed shifts with any discrepancy flagged, and the day's totals.
     *
     * @return array{open_shifts: int, closed_shifts: int, discrepancy_shifts: int, total_discrepancy: int}
     */
    public function dashboardKpis(Branch $branch): array
    {
        $from = now()->startOfDay();
        $to = now()->endOfDay();

        $shifts = StaffShift::query()
            ->where('branch_id', $branch->id)
            ->where(function ($query) use ($from, $to): void {
                $query
                    ->whereBetween('opened_at', [$from, $to])
                    ->orWhereBetween('closed_at', [$from, $to])
                    ->orWhere(function ($openQuery) use ($to): void {
                        $openQuery->whereNull('closed_at')->where('opened_at', '<=', $to);
                    });
            })
            ->get(['discrepancy', 'closed_at']);

        $discrepancyShifts = $shifts
            ->filter(fn (StaffShift $shift) => $shift->isOpen() === false && $shift->discrepancy !== 0)
            ->count();

        return [
            'open_shifts' => $shifts->filter(fn (StaffShift $shift) => $shift->isOpen())->count(),
            'closed_shifts' => $shifts->filter(fn (StaffShift $shift) => $shift->isOpen() === false)->count(),
            'discrepancy_shifts' => $discrepancyShifts,
            'total_discrepancy' => (int) $shifts->sum('discrepancy'),
        ];
    }

    /**
     * One shift row as the EOD board presents it.
     *
     * @return array<string, mixed>
     */
    protected function presentShift(StaffShift $shift): array
    {
        return [
            'id' => $shift->id,
            'cashier' => $shift->user?->name ?? '—',
            'station' => $shift->station,
            'is_kitchen' => ! $shift->settlesCash(),
            'opened_at' => $shift->opened_at->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'is_open' => $shift->isOpen(),
            'opening_cash' => $shift->opening_cash,
            'cash_payments' => $shift->cashPaymentsTotal(),
            'card_payments' => $shift->cardPaymentsTotal(),
            'movements_net' => $shift->cashMovementsNet(),
            'expected_cash' => $shift->expected_cash,
            'counted_cash' => $shift->closing_cash,
            'discrepancy' => $shift->discrepancy,
            'closed_by' => $shift->closer?->name,
        ];
    }

    /**
     * The day's order counters: placed, paid, and still-open tickets.
     *
     * @return array<string, int>
     */
    protected function orderKpis(Branch $branch, Carbon $from, Carbon $to): array
    {
        $base = Order::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('placed_at', [$from, $to]);

        $placed = (clone $base)->count();
        $paid = (clone $base)->whereHas('payments')->count();
        $cancelled = (clone $base)->where('status', OrderStatus::Cancelled)->count();

        return [
            'placed' => $placed,
            'paid' => $paid,
            'cancelled' => $cancelled,
        ];
    }
}
