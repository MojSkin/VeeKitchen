<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'پخش مواد غذایی نور', 'تامین‌کننده لبنیات پارس', 'گوشت سرای امید',
            'سبزیجات بهاران', 'شرکت پخش نوشیدنی چشمه',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($names),
            'phone' => '021-44'.$this->faker->numerify('######'),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
