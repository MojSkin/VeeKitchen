<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Cashier\ShiftController;
use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * The kitchen's presence-only shift: entry (open) and exit (close) with a
 * zero float and no cash movements. Settlement stays at the cashier — the
 * `station` column marks the shift so the payment guard refuses to stamp
 * kitchen shifts, and the EOD board labels them.
 */
class KitchenShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shifts,
    ) {}

    /**
     * Entry: open a kitchen-station shift with a zero float. Shares the
     * branch resolution and messaging vocabulary with the cashier panel.
     */
    public function open(Request $request): RedirectResponse
    {
        try {
            $this->shifts->open(
                $request->user(),
                $this->branchId($request->user()),
                0,
                'kitchen',
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'شیفت آشپزخانه باز شد. خوش آمدید!');
    }

    /**
     * Exit: close the kitchen shift. No cash is counted — the zero float
     * is passed through and the settlement reads a zero discrepancy.
     */
    public function close(Request $request): RedirectResponse
    {
        $shift = $this->shifts->openShiftFor($request->user(), $this->branchId($request->user()));

        if ($shift === null) {
            return back()->with('error', 'شیفت بازی ندارید.');
        }

        try {
            $this->shifts->close($shift, $request->user(), 0);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'شیفت آشپزخانه بسته شد. خسته نباشید!');
    }

    /**
     * Same fallback rule as the cashier panel (ShiftController::branchId).
     */
    protected function branchId($user): int
    {
        return app(ShiftController::class)->branchIdFor($user);
    }
}
