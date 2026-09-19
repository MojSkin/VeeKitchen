<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'شعبه '.$this->faker->unique()->city();

        return [
            'name' => $name,
            'slug' => $this->faker->unique()->slug(2),
            'phone' => '021-88'.$this->faker->numerify('######'),
            'address' => $this->faker->streetAddress(),
            'is_active' => true,
        ];
    }
}
