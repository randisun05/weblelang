<?php

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0812'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Bidder,
            'kyc_status' => KycStatus::Unsubmitted,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'kyc_status' => KycStatus::Verified,
            'kyc_verified_at' => now(),
            'nik' => fake()->numerify('################'),
        ]);
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role, 'two_factor_confirmed_at' => now()]);
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }

    public function staff(): static
    {
        return $this->role(Role::Staff);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
