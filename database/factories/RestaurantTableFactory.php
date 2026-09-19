<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\Branch;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'label' => 'میز '.$this->faker->unique()->numberBetween(1, 60),
            'capacity' => $this->faker->numberBetween(2, 8),
            'qr_token' => bin2hex(random_bytes(32)),
            'status' => TableStatus::Free,
            'occupied_at' => null,
        ];
    }

    public function reserved(): static
    {
        return $this->state(fn () => [
            'status' => TableStatus::Reserved,
            'occupied_at' => now(),
        ]);
    }
}
