<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'menu_category_id',
        'name',
        'description',
        'price',
        'image_path',
        'prep_minutes',
        'position',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_available' => 'boolean',
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
     * @return BelongsTo<MenuCategory, $this>
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * @return HasMany<ProductRecipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    /**
     * @return HasMany<CostComponent, $this>
     */
    public function costComponents(): HasMany
    {
        return $this->hasMany(CostComponent::class)->orderBy('position');
    }

    /**
     * Only products the customer can actually order right now.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }
}
