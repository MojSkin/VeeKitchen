<?php

namespace App\Enums;

use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

enum DiscountScope: string
{
    case EntireOrder = 'entire_order';
    case Category = 'category';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::EntireOrder => 'کل سفارش',
            self::Category => 'یک دستهٔ منو',
            self::Product => 'یک محصول',
        };
    }

    /**
     * The model class this scope points at (null for entire orders).
     *
     * @return class-string<Model>|null
     */
    public function targetClass(): ?string
    {
        return match ($this) {
            self::EntireOrder => null,
            self::Category => MenuCategory::class,
            self::Product => Product::class,
        };
    }

    /**
     * The discount row this scope reads its target from.
     */
    public function targetColumn(): string
    {
        return match ($this) {
            self::EntireOrder => '',
            self::Category => 'menu_category_id',
            self::Product => 'product_id',
        };
    }

    /**
     * Whether this discount may apply to the given line's product.
     */
    public function canApplyTo(Discount $discount, Product $product): bool
    {
        return match ($this) {
            self::EntireOrder => true,
            self::Category => $product->menu_category_id !== null
                && $product->menu_category_id === $discount->menu_category_id,
            self::Product => $product->id === $discount->product_id,
        };
    }
}
