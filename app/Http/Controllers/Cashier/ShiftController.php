<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\StaffShift;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use InvalidArgumentException;
use RuntimeException;

/**
 * The cashier's shift panel: open with a float, document cash movements,
 * close with a counted balance and see the discrepancy.
 */
class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shifts,
    ) {}

    /**
     * The standalone shift panel page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $this->branchId($user);
        $shift = $this->shifts->openShiftFor($user, $branchId);

        return Inertia::render('Cashier/Shift', [
            'branchId' => $branchId,
            'shift' => $shift === null ? null : $this->present($shift),
            'history' => $this->historyPayload($user, $branchId),
        ]);
    }

    /**
     * Open a shift with the drawer's starting float.
     */
    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $this->shifts->open($request->user(), $this->branchId($request->user()), (int) $validated['opening_cash']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'شیفت با موفقیت باز شد. پرداخت‌ها به این شیفت ثبت می‌شوند.');
    }

    /**
     * Record a documented cash movement in the open shift.
     */
    public function movement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:withdrawal,deposit,adjustment'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $shift = $this->shifts->openShiftFor($request->user(), $this->branchId($request->user()));

        if ($shift === null) {
            return back()->with('error', 'شیفت بازی ندارید؛ ابتدا شیفت را باز کنید.');
        }

        try {
            $this->shifts->registerMovement($shift, $request->user(), $validated['type'], (int) $validated['amount'], $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'حرکت نقدی ثبت شد.');
    }

    /**
     * Close the shift with a counted balance; the discrepancy is frozen —
     * or reconciled through a ledger-only adjustment row when the closer
     * ticks "جبران دفتری".
     */
    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0'],
            'compensate' => ['nullable', 'boolean'],
            'compensation_reason' => ['required_if:compensate,true', 'nullable', 'string', 'max:500'],
        ]);

        $shift = $this->shifts->openShiftFor($request->user(), $this->branchId($request->user()));

        if ($shift === null) {
            return back()->with('error', 'شیفت بازی ندارید.');
        }

        $compensate = (bool) ($validated['compensate'] ?? false);

        try {
            $result = $this->shifts->close(
                $shift,
                $request->user(),
                (int) $validated['counted_cash'],
                $compensate,
                $validated['compensation_reason'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $discrepancy = $result['discrepancy'];

        $message = $discrepancy === 0
            ? ($compensate ? 'شیفت با جبران دفتریِ مغایرت بسته شد؛ صندوقِ انتظار با شمارش آشتی شد.' : 'شیفت با مغایرت صفر بسته شد.')
            : ($discrepancy > 0
                ? 'شیفت بسته شد؛ مازاد صندوق: '.number_format($discrepancy).' تومان.'
                : 'شیفت بسته شد؛ کسری صندوق: '.number_format(abs($discrepancy)).' تومان.');

        return back()->with('success', $message);
    }

    /**
     * The shift card shape for the panel.
     *
     * @return array<string, mixed>
     */
    protected function present(StaffShift $shift): array
    {
        return [
            'id' => $shift->id,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'opening_cash' => $shift->opening_cash,
            'cash_payments' => $shift->cashPaymentsTotal(),
            'card_payments' => $shift->cardPaymentsTotal(),
            'movements_net' => $shift->cashMovementsNet(),
            'movements_count' => $shift->cashMovements()->count(),
            'expected_cash' => $shift->computeExpectedCash(),
        ];
    }

    /**
     * The user's last closed shifts (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function historyPayload($user, int $branchId): array
    {
        return StaffShift::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branchId)
            ->whereNotNull('closed_at')
            ->with('cashMovements')
            ->latest('closed_at')
            ->limit(10)
            ->get()
            ->map(fn (StaffShift $shift) => [
                'id' => $shift->id,
                'opened_at' => $shift->opened_at?->toIso8601String(),
                'closed_at' => $shift->closed_at?->toIso8601String(),
                'opening_cash' => $shift->opening_cash,
                'cash_payments' => $shift->cashPaymentsTotal(),
                'card_payments' => $shift->cardPaymentsTotal(),
                'movements_net' => $shift->cashMovementsNet(),
                'expected_cash' => $shift->expected_cash,
                'counted_cash' => $shift->closing_cash,
                'discrepancy' => $shift->discrepancy,
            ])
            ->values()
            ->all();
    }

    /**
     * Staff without a branch fall back to the first active branch.
     */
    protected function branchId($user): int
    {
        if ($user->branch_id !== null) {
            return $user->branch_id;
        }

        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();

        abort_if($branch === null, 503, 'هیچ شعبه فعالی ثبت نشده است.');

        return $branch->id;
    }

    /**
     * Public hook for sibling controllers (kitchen shift) that share the
     * branch resolution but live behind a different role middleware.
     */
    public function branchIdFor($user): int
    {
        return $this->branchId($user);
    }
}
