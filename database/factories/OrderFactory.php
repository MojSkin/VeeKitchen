<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomElement([360_000, 640_000, 1_240_000]);
        $total = (int) (ceil($subtotal / 100) * 100);

        return [
            'branch_id' => Branch::factory(),
            'restaurant_table_id' => null,
            'customer_id' => null,
            'guest_token' => bin2hex(random_bytes(20)),
            'guest_name' => $this->faker->firstName(),
            'order_number' => null,
            'status' => OrderStatus::AwaitingPayment,
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'total' => $total,
            'notes' => null,
            'placed_at' => now(),
        ];
    }

    public function forTable(?RestaurantTable $table): static
    {
        return $this->state(fn () => ['restaurant_table_id' => $table?->id]);
    }

    public function forCustomer(?User $customer): static
    {
        return $this->state(fn () => ['customer_id' => $customer?->id]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Queued,
            'order_number' => $this->faker->numberBetween(1, 40),
            'paid_at' => now(),
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Preparing]);
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Ready,
            'ready_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(?string $reason = 'تست'): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Cancelled,
            'cancel_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }
}
