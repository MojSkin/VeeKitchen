<?php

namespace App\Models;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A branch-level discount: automatic (no code) or coupon, on the entire
 * order, a menu category, or a single product — with an optional validity
 * window and usage ceilings.
 */
class Discount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'type',
        'value',
        'applies_to',
        'menu_category_id',
        'product_id',
        'min_order_total',
        'starts_at',
        'expires_at',
        'usage_limit_total',
        'usage_limit_per_user',
        'used_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'applies_to' => DiscountScope::class,
            'value' => 'integer',
            'min_order_total' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_limit_total' => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count' => 'integer',
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
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whether the discount's window covers the given moment.
     */
    public function isActiveAt(?Carbon $at = null): bool
    {
        $at ??= now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at !== null && $at->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the discount has any usage headroom left (global ceiling).
     */
    public function hasTotalHeadroom(): bool
    {
        return $this->usage_limit_total === null
            || $this->used_count < $this->usage_limit_total;
    }

    /**
     * Whether a given user still has per-user headroom (null user = guest).
     */
    public function hasHeadroomFor(?int $userId): bool
    {
        if ($this->usage_limit_per_user === null) {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        return Order::query()
            ->where('discount_id', $this->id)
            ->where('customer_id', $userId)
            ->count() < $this->usage_limit_per_user;
    }

    /**
     * Whether the given cart subtotal clears the minimum.
     */
    public function meetsMinOrder(int $subtotalToman): bool
    {
        return $subtotalToman >= $this->min_order_total;
    }

    /**
     * Active discounts of a branch, newest first.
     *
     * @param  Builder<Discount>  $query
     */
    public function scopeActive(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId)->where('is_active', true);
    }
}
