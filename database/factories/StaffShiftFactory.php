<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffShift>
 */
class StaffShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'opened_at' => now()->subHours(4),
            'closed_at' => null,
            'opening_cash' => 500_000,
            'closing_cash' => null,
            'expected_cash' => null,
            'discrepancy' => null,
            'closed_by' => null,
        ];
    }

    /**
     * A closed shift with a balanced till.
     */
    public function closed(): static
    {
        return $this->state(fn () => [
            'closed_at' => now()->subHour(),
            'opening_cash' => 500_000,
            'closing_cash' => 500_000,
            'expected_cash' => 500_000,
            'discrepancy' => 0,
            'closed_by' => User::factory(),
        ]);
    }

    /**
     * Start the till with a specific float.
     */
    public function openingCash(int $amount): static
    {
        return $this->state(fn () => ['opening_cash' => $amount]);
    }
}
