<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A documented in-shift cash movement (withdrawal, deposit or ledger-only
 * adjustment) — the shift's expected balance folds these in.
 */
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'user_id',
        'type',
        'amount',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StaffShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(StaffShift::class, 'shift_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
