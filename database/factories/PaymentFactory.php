<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'received_by' => User::factory()->cashier(),
            'method' => $this->faker->randomElement([PaymentMethod::Cash, PaymentMethod::Card]),
            'amount' => $this->faker->randomElement([360_000, 640_000, 1_240_000]),
            'gateway_reference' => null,
            'paid_at' => now(),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => ['method' => PaymentMethod::Cash]);
    }
}
