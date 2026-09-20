<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => StaffShift::factory(),
            'user_id' => User::factory(),
            'type' => CashMovementType::Withdrawal,
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'reason' => $this->faker->randomElement(['واریز به خزانه', 'خرید فوری', 'اصلاح شمارش']),
        ];
    }

    public function withdrawal(int $amount, ?string $reason = null): static
    {
        return $this->state(fn () => [
            'type' => CashMovementType::Withdrawal,
            'amount' => $amount,
            'reason' => $reason ?? 'واریز به خزانه',
        ]);
    }

    public function deposit(int $amount, ?string $reason = null): static
    {
        return $this->state(fn () => [
            'type' => CashMovementType::Deposit,
            'amount' => $amount,
            'reason' => $reason ?? 'شارژ صندوق',
        ]);
    }

    public function adjustment(int $amount, ?string $reason = null): static
    {
        return $this->state(fn () => [
            'type' => CashMovementType::Adjustment,
            'amount' => $amount,
            'reason' => $reason ?? 'اصلاح شمارش',
        ]);
    }
}
