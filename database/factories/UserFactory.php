<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** The current password being used by the factory. */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0912'.fake()->numerify('#######'),
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function cashier(): static
    {
        return $this->state(fn () => ['role' => UserRole::Cashier]);
    }

    public function kitchen(): static
    {
        return $this->state(fn () => ['role' => UserRole::Kitchen]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Customer]);
    }

    /**
     * Staff pinned to a specific branch (admins skip it).
     */
    public function forBranch(?Branch $branch): static
    {
        return $this->state(fn () => [
            'branch_id' => $branch?->id,
        ]);
    }
}
