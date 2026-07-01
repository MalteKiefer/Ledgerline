<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IncomeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeEntry>
 */
class IncomeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->sentence(3),
            'amount_cents' => fake()->numberBetween(5000, 500000),
            'currency' => 'EUR',
            'billed' => false,
        ];
    }
}
