<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'unit',
        'unit_cost',
        'current_stock',
        'low_stock_threshold',
        'qr_label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit' => MeasurementUnit::class,
            'unit_cost' => 'integer',
            'current_stock' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'is_active' => 'boolean',
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
     * @return HasMany<ProductRecipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Whether the low-stock alert should fire right now.
     */
    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->low_stock_threshold;
    }
}
