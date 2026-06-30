<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', '+1 month');

        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->catchPhrase(),
            'reference' => strtoupper(fake()->unique()->bothify('PRJ-####')),
            'type' => fake()->randomElement(ProjectType::cases()),
            'priority' => fake()->randomElement(ProjectPriority::cases()),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'estimated_hours' => fake()->optional()->randomFloat(2, 1, 400),
            'description' => fake()->optional()->paragraph(),
            'start_date' => $startDate,
            'end_date' => fake()->optional()->dateTimeBetween($startDate, '+1 year'),
            'budget' => fake()->optional()->randomFloat(2, 1000, 500000),
        ];
    }
}
