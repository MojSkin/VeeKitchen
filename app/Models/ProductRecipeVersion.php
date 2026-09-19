<?php

namespace App\Models;

use Database\Factories\ProductRecipeVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a product's recipe at the moment it was saved:
 * lines with the unit costs of that day, the cost-component chain, and the
 * three §5-rounded price figures. Versions never mutate — history only grows.
 */
class ProductRecipeVersion extends Model
{
    /** @use HasFactory<ProductRecipeVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'created_by',
        'version_number',
        'lines',
        'components',
        'material_cost',
        'cost_price',
        'suggested_sale_price',
    ];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'components' => 'array',
            'material_cost' => 'integer',
            'cost_price' => 'integer',
            'suggested_sale_price' => 'integer',
            'version_number' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
