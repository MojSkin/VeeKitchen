<?php

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'آرد گندم', 'پنیر موزارلا', 'سس گوجه', 'گوشت چرخ‌کرده', 'فیله مرغ',
            'قارچ', 'فلفل دلمه', 'پیاز', 'روغن سرخ‌کردنی', 'نان بریوش',
            'کاهو', 'پپرونی', 'قوطی نوشابه', 'پرتقال',
        ];

        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->unique()->randomElement($names),
            'unit' => $this->faker->randomElement([
                MeasurementUnit::Kilogram,
                MeasurementUnit::Gram,
                MeasurementUnit::Liter,
                MeasurementUnit::Piece,
            ]),
            'current_stock' => $this->faker->randomFloat(3, 10, 200),
            'low_stock_threshold' => $this->faker->randomFloat(3, 1, 10),
            'qr_label' => $this->faker->unique()->regexify('[A-Z0-9]{12}'),
            'is_active' => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'current_stock' => 2.0,
            'low_stock_threshold' => 5.0,
        ]);
    }

    public function withoutQr(): static
    {
        return $this->state(fn () => ['qr_label' => null]);
    }
}
