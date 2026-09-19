<?php

namespace App\Models;

use Database\Factories\WasteLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded material loss (spoilage, breakage, expiry, ...). Creating a row
 * here is the manual twin of the automatic Consumption ledger entries.
 */
class WasteLog extends Model
{
    /** @use HasFactory<WasteLogFactory> */
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'branch_id',
        'user_id',
        'quantity',
        'reason',
        'expired_on',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'expired_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
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
}
