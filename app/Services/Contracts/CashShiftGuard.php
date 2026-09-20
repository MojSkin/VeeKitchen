<?php

namespace App\Services\Contracts;

use App\Models\StaffShift;
use App\Models\User;

/**
 * Resolves the open cash shift a payment must hang on. The phase-3 golden
 * rule — money only moves inside an open shift — flows through here, so
 * alternative guard policies can replace ShiftService without touching the
 * payment pipeline.
 */
interface CashShiftGuard
{
    /**
     * The user's open shift in the branch, or a failure explaining why a
     * payment cannot proceed.
     */
    public function requireOpenShift(User $receiver, int $branchId): StaffShift;
}
