<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\StaffShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff shift: the audit anchor every payment, cash movement and
 * settlement hangs on. Golden rule — money only moves inside an open shift.
 */
class StaffShift extends Model
{
    /** @use HasFactory<StaffShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'discrepancy',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'closing_cash' => 'integer',
            'expected_cash' => 'integer',
            'discrepancy' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'shift_id');
    }

    /**
     * Whether the shift is still open.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * The cash payments received inside this shift.
     */
    public function cashPaymentsTotal(): int
    {
        return (int) $this->payments()
            ->where('method', PaymentMethod::Cash->value)
            ->sum('amount');
    }

    /**
     * The card payments received inside this shift (never in the drawer).
     */
    public function cardPaymentsTotal(): int
    {
        return (int) $this->payments()
            ->where('method', PaymentMethod::Card->value)
            ->sum('amount');
    }

    /**
     * The net effect of the shift's manual cash movements.
     */
    public function cashMovementsNet(): int
    {
        return (int) $this->cashMovements()
            ->get()
            ->sum(fn (CashMovement $movement) => $movement->type->sign() * $movement->amount);
    }

    /**
     * The drawer balance the counted cash is compared against:
     * opening + cash payments − withdrawals + deposits/adjustments.
     */
    public function computeExpectedCash(): int
    {
        return $this->opening_cash
            + $this->cashPaymentsTotal()
            + $this->cashMovementsNet();
    }
}
