<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory()->admin(),
            'received_by' => null,
            'status' => PurchaseOrderStatus::Draft,
            'total' => 0,
            'notes' => $this->faker->optional()->sentence(),
            'ordered_at' => null,
            'expected_at' => $this->faker->optional()->dateTimeBetween('+1 day', '+7 days'),
            'received_at' => null,
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Received,
            'ordered_at' => now()->subDays(2),
            'received_at' => now(),
            'received_by' => User::factory()->admin(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Cancelled]);
    }
}
