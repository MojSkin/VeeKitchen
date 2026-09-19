<?php

namespace Database\Factories;

use App\Enums\CostComponentType;
use App\Models\CostComponent;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostComponent>
 */
class CostComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $labels = ['مالیات بر ارزش افزوده', 'سود مدیریت', 'سربار', 'بسته‌بندی', 'عوارض'];

        return [
            'product_id' => Product::factory(),
            'label' => $this->faker->unique()->randomElement($labels),
            'type' => $this->faker->randomElement([CostComponentType::Fixed, CostComponentType::Percent]),
            'value' => $this->faker->randomElement([10_000, 20_000, 500, 900]),
            'is_cost' => false,
            'position' => $this->faker->numberBetween(0, 20),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }

    public function fixed(int $toman): static
    {
        return $this->state(fn () => ['type' => CostComponentType::Fixed, 'value' => $toman]);
    }

    public function costBearing(): static
    {
        return $this->state(fn () => ['is_cost' => true]);
    }

    public function percent(float $percent): static
    {
        return $this->state(fn () => [
            'type' => CostComponentType::Percent,
            'value' => (int) round($percent * 100),
        ]);
    }
}
