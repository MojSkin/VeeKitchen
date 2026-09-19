<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WasteLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteLog>
 */
class WasteLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = [
            'تاریخ انقضا گذشته', 'آسیب حین جابه‌جایی', 'سوختن در آشپزخانه',
            'برگشت مشتری', 'خرابی یخچال',
        ];

        return [
            'inventory_item_id' => InventoryItem::factory(),
            'branch_id' => Branch::factory(),
            'user_id' => User::factory()->kitchen(),
            'quantity' => $this->faker->randomFloat(3, 0.1, 5),
            'reason' => $this->faker->randomElement($reasons),
            'expired_on' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'reason' => 'تاریخ انقضا گذشته',
            'expired_on' => $this->faker->dateTimeBetween('-10 days', '-1 day')->format('Y-m-d'),
        ]);
    }
}
