<?php

namespace App\Models;

use App\Enums\CostComponentType;
use Database\Factories\CostComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered surcharge on a product's material cost — tax, management
 * profit, overhead, packaging, ... Percent components use basis points
 * (100 = 1%) so no float ever touches money.
 */
class CostComponent extends Model
{
    /** @use HasFactory<CostComponentFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'label',
        'type',
        'value',
        'is_cost',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => CostComponentType::class,
            'value' => 'integer',
            'is_cost' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Percent values are stored as basis points; this is the plain % figure.
     */
    public function percent(): float
    {
        return $this->value / 100;
    }
}
