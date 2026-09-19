<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->unique()->randomElement([
                'پیتزا', 'برگر', 'کباب', 'پیش‌غذا', 'نوشیدنی', 'دسر',
            ]).' '.$this->faker->numerify('#'),
            'description' => $this->faker->sentence(),
            'position' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
        ];
    }
}
