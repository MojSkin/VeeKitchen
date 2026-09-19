<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single (product, material, quantity-per-unit) recipe line.
 */
class ProductRecipe extends Model
{
    /** @use HasFactory<ProductRecipeFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'inventory_item_id',
        'quantity_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_unit' => 'decimal:3',
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
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
