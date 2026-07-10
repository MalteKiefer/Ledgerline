<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DevicePairing>
 */
class DevicePairingFactory extends Factory
{
    protected $model = DevicePairing::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_hash' => hash('sha256', Str::random(43)),
            'device_name' => null,
            'status' => DevicePairing::PENDING_SCAN,
            'token_id' => null,
            'expires_at' => now()->addMinutes(2),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
