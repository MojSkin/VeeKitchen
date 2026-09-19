<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'پیتزا مارگاریتا', 'پیتزا پپرونی', 'پیتزا مخلوط',
            'چیزبرگر', 'دوبل برگر', 'برگر مرغ',
            'کباب کوبیده', 'جوجه کباب', 'شیشلیک',
            'سیب‌زمینی سرخ‌کرده', 'سالاد فصل', 'سوخاری مرغ',
            'نوشابه', 'دوغ', 'آب‌میوه طبیعی', 'چای',
            'بستنی', 'مهلبی',
        ];

        return [
            'branch_id' => Branch::factory(),
            'menu_category_id' => MenuCategory::factory(),
            'name' => $this->faker->unique()->randomElement($names),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomElement([180_000, 250_000, 320_000, 450_000, 620_000, 980_000]),
            'prep_minutes' => $this->faker->numberBetween(5, 25),
            'position' => $this->faker->numberBetween(0, 50),
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
