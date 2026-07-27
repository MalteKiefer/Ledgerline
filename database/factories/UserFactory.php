<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state — a first-party email+password user.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // hashed via the model's 'password' => 'hashed' cast
            'role' => 'user',
            'avatar' => null,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /** An admin user (may manage workspace-wide settings). */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => 'admin']);
    }

    /** Indicate that the model's email address should be unverified. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => ['email_verified_at' => null]);
    }
}
