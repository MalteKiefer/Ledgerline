<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\IncomeEntry;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_report(): void
    {
        $this->get(route('finance.report'))->assertRedirect(route('login'));
    }

    public function test_report_computes_income_expenses_and_profit(): void
    {
        $this->signIn();

        Invoice::factory()->create(['status' => 'SENT', 'finalized_at' => now(), 'net_cents' => 10000, 'gross_cents' => 11900, 'issue_date' => now()]);
        Invoice::factory()->create(['status' => 'DRAFT', 'net_cents' => 99999, 'issue_date' => now()]); // excluded
        Expense::factory()->create(['amount_cents' => 4760, 'tax_cents' => 760, 'date' => now()]); // net 4000
        IncomeEntry::factory()->create(['amount_cents' => 2000, 'date' => now()]);

        $this->get(route('finance.report'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s): bool => $s['income'] === 12000 && $s['expenses'] === 4000 && $s['profit'] === 8000);
    }

    public function test_report_respects_the_date_range(): void
    {
        $this->signIn();

        Invoice::factory()->create(['status' => 'SENT', 'finalized_at' => now(), 'net_cents' => 5000, 'issue_date' => now()->subYears(2)]);

        $this->get(route('finance.report'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s): bool => $s['income'] === 0);
    }
}
