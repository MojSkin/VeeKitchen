<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\Contracts\CashShiftGuard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The shift domain: opening, cash movements, settlement and the golden
 * phase-3 rule — money only moves inside an open shift.
 *
 * Concurrency: open/close run inside transactions with the shift row
 * locked, so two tabs can never open or close the same shift twice.
 */
class ShiftService implements CashShiftGuard
{
    /**
     * The acting user's open shift in a branch, or null.
     */
    public function openShiftFor(User $user, int $branchId): ?StaffShift
    {
        /** @var StaffShift|null $shift */
        $shift = StaffShift::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branchId)
            ->whereNull('closed_at')
            ->first();

        return $shift;
    }

    /**
     * Open a shift for the user in a branch — one open shift per
     * branch+user, enforced inside a transaction.
     */
    public function open(User $user, int $branchId, int $openingCash): StaffShift
    {
        if ($openingCash < 0) {
            throw new InvalidArgumentException('موجودی اولیهٔ صندوق نمی‌تواند منفی باشد.');
        }

        return DB::transaction(function () use ($user, $branchId, $openingCash): StaffShift {
            // Lock the user's existing rows so a double-open race
            // serializes here.
            $existing = StaffShift::query()
                ->where('user_id', $user->id)
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new RuntimeException('شما یک شیفت باز دارید؛ ابتدا آن را ببندید.');
            }

            return StaffShift::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
            ]);
        });
    }

    /**
     * Close a shift: freeze the expected balance, store the counted cash
     * and the discrepancy, all inside a locked transaction.
     *
     * @return array{shift: StaffShift, expected: int, counted: int, discrepancy: int, cash: int, card: int, movements_net: int}
     */
    public function close(StaffShift $shift, User $actor, int $countedCash): array
    {
        return DB::transaction(function () use ($shift, $actor, $countedCash): array {
            /** @var StaffShift $locked */
            $locked = StaffShift::query()
                ->whereKey($shift->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('این شیفت قبلاً بسته شده است.');
            }

            // Payments settling against a closing shift must be inside the
            // balance — the guard forbids new ones from here on anyway.
            $expected = $locked->computeExpectedCash();

            $locked->expected_cash = $expected;
            $locked->closing_cash = $countedCash;
            $locked->discrepancy = $countedCash - $expected;
            $locked->closed_at = now();
            $locked->closed_by = $actor->id;
            $locked->save();

            return [
                'shift' => $locked,
                'expected' => $expected,
                'counted' => $countedCash,
                'discrepancy' => $locked->discrepancy,
                'cash' => $locked->cashPaymentsTotal(),
                'card' => $locked->cardPaymentsTotal(),
                'movements_net' => $locked->cashMovementsNet(),
            ];
        });
    }

    /**
     * Record a documented cash movement inside the open shift.
     */
    public function registerMovement(StaffShift $shift, User $user, string $type, int $amount, string $reason): CashMovement
    {
        $movementType = CashMovementType::from($type);

        if (! $shift->isOpen()) {
            throw new RuntimeException('شیفت بسته است؛ حرکت نقدی ثبت نمی‌شود.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ حرکت نقدی باید مثبت باشد.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('ثبت دلیل حرکت نقدی الزامی است.');
        }

        return DB::transaction(function () use ($shift, $user, $movementType, $amount, $reason): CashMovement {
            /** @var StaffShift $locked */
            $locked = StaffShift::query()
                ->whereKey($shift->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('شیفت بسته است؛ حرکت نقدی ثبت نمی‌شود.');
            }

            // A withdrawal can never send the theoretical drawer negative —
            // that would mean paying out money the drawer does not hold.
            if ($movementType === CashMovementType::Withdrawal
                && $locked->opening_cash + $locked->cashPaymentsTotal() + $locked->cashMovementsNet() - $amount < 0) {
                throw new RuntimeException('برداشت بیشتر از موجودی فعلی صندوق ممکن نیست.');
            }

            return $locked->cashMovements()->create([
                'user_id' => $user->id,
                'type' => $movementType,
                'amount' => $amount,
                'reason' => trim($reason),
            ]);
        });
    }

    /**
     * The golden rule: a payment may only settle inside the receiver's
     * open shift. Returns the shift to stamp on the payment.
     */
    public function requireOpenShift(User $receiver, int $branchId): StaffShift
    {
        $shift = $this->openShiftFor($receiver, $branchId);

        if ($shift === null) {
            throw new RuntimeException('برای دریافت پرداخت، ابتدا شیفت خود را باز کنید.');
        }

        return $shift;
    }

    /**
     * The settlement summary of a closed shift — the report/eod shape.
     *
     * @return array<string, int|null>
     */
    public function settlementSummary(StaffShift $shift): array
    {
        return [
            'opening_cash' => $shift->opening_cash,
            'cash_payments' => $shift->cashPaymentsTotal(),
            'card_payments' => $shift->cardPaymentsTotal(),
            'movements_net' => $shift->cashMovementsNet(),
            'expected_cash' => $shift->expected_cash,
            'counted_cash' => $shift->closing_cash,
            'discrepancy' => $shift->discrepancy,
        ];
    }
}
