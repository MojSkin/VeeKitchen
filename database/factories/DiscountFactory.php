<?php

namespace Database\Factories;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->randomElement([
                'جشنوارهٔ پیتزا', 'خوش‌آمدگویی', 'تخفیف نوشیدنی', 'پیشنهاد ویژهٔ ناهار',
            ]),
            'code' => null,
            'type' => DiscountType::Percentage,
            'value' => $this->faker->randomElement([10, 15, 20, 25]),
            'applies_to' => DiscountScope::EntireOrder,
            'menu_category_id' => null,
            'product_id' => null,
            'min_order_total' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit_total' => null,
            'usage_limit_per_user' => null,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    /** Coupon discount with a unique code. */
    public function withCode(?string $code = null): static
    {
        return $this->state(fn () => [
            'code' => $code ?? strtoupper($this->faker->unique()->lexify('????')),
        ]);
    }

    public function percent(int $value): static
    {
        return $this->state(fn () => [
            'type' => DiscountType::Percentage,
            'value' => $value,
        ]);
    }

    public function fixed(int $value): static
    {
        return $this->state(fn () => [
            'type' => DiscountType::Fixed,
            'value' => $value,
        ]);
    }

    public function forCategory(?MenuCategory $category = null): static
    {
        return $this->state(fn () => [
            'applies_to' => DiscountScope::Category,
            'menu_category_id' => $category?->id ?? MenuCategory::factory(),
            'product_id' => null,
        ]);
    }

    public function forProduct(?Product $product = null): static
    {
        return $this->state(fn () => [
            'applies_to' => DiscountScope::Product,
            'product_id' => $product?->id ?? Product::factory(),
            'menu_category_id' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => [
            'usage_limit_total' => 5,
            'used_count' => 5,
        ]);
    }

    public function minOrder(int $subtotal): static
    {
        return $this->state(fn () => ['min_order_total' => $subtotal]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
