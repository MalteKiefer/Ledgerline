<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'INVOICE',
            'status' => 'DRAFT',
            'customer_id' => Customer::factory(),
            'issue_date' => now()->toDateString(),
            'language' => 'de',
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'payment_terms_days' => 14,
            'net_cents' => 0,
            'tax_cents' => 0,
            'gross_cents' => 0,
        ];
    }
}
