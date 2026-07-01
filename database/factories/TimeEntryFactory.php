<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->sentence(3),
            'minutes' => fake()->numberBetween(15, 480),
            'rate_cents' => fake()->randomElement([7500, 9000, 12000]),
            'currency' => 'EUR',
            'billable' => true,
            'billed' => false,
        ];
    }
}
