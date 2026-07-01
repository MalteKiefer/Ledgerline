<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = fake()->numberBetween(500, 200000);
        $rate = fake()->randomElement([0, 7, 19]);
        $tax = (int) round($gross * $rate / (100 + $rate));

        return [
            'date' => fake()->dateTimeBetween('-6 months', 'now'),
            'description' => fake()->sentence(3),
            'vendor' => fake()->company(),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'amount_cents' => $gross,
            'currency' => 'EUR',
            'tax_rate' => $rate,
            'tax_cents' => $tax,
            'payment_status' => fake()->randomElement(PaymentStatus::cases()),
            'billable' => fake()->boolean(30),
            'billed' => false,
        ];
    }
}
