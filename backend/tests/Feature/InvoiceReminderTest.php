<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\FinanceReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Overdue aging buckets + the invoices:remind scheduler: it picks unpaid overdue
 * invoices, stamps reminded_at / reminder_count, is idempotent on an immediate
 * re-run, re-arms when the due date is pushed out, and skips not-yet-due ones.
 */
class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    private function open(User $user, string $due, array $attrs = []): Invoice
    {
        $this->actingAs($user);

        return Invoice::create(array_merge([
            'number' => '1', 'year' => 2026, 'status' => 'sent', 'imported' => false,
            'issue_date' => '2026-01-01', 'due_date' => $due, 'currency' => 'EUR',
            'gross' => 119, 'net' => 100, 'vat' => 19,
            'customer' => ['name' => 'ACME'],
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
        ], $attrs));
    }

    public function test_aging_buckets_are_correct(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();
        $this->open($user, $today->copy()->subDays(10)->toDateString());                 // 1_30
        $this->open($user, $today->copy()->subDays(40)->toDateString(), ['number' => '2']); // 31_60
        $this->open($user, $today->copy()->subDays(90)->toDateString(), ['number' => '3']); // 60_plus
        $this->open($user, $today->copy()->addDays(5)->toDateString(), ['number' => '4']);  // current (not yet due)
        $this->open($user, $today->copy()->subDays(3)->toDateString(), ['number' => '5', 'status' => 'paid']); // excluded (paid)

        $aging = app(FinanceReports::class)->aging();

        $this->assertSame(4, $aging['openCount']); // the paid one is excluded
        $this->assertSame(1, $aging['buckets']['1_30']['count']);
        $this->assertSame(1, $aging['buckets']['31_60']['count']);
        $this->assertSame(1, $aging['buckets']['60_plus']['count']);
        $this->assertSame(1, $aging['buckets']['current']['count']);
        $this->assertEqualsWithDelta(119.0, $aging['buckets']['1_30']['gross'], 0.001);
    }

    public function test_remind_picks_overdue_and_stamps_then_is_idempotent(): void
    {
        $user = User::factory()->create();
        $inv = $this->open($user, Carbon::today()->copy()->subDays(10)->toDateString());

        $this->artisan('invoices:remind')->assertOk();
        $inv->refresh();
        $this->assertNotNull($inv->reminded_at);
        $this->assertSame(1, $inv->reminder_count);

        // Immediate second run: within the last 7 days + not before due → no-op.
        $this->artisan('invoices:remind')->assertOk();
        $inv->refresh();
        $this->assertSame(1, $inv->reminder_count);
    }

    public function test_remind_re_arms_on_due_date_change(): void
    {
        $user = User::factory()->create();
        $inv = $this->open($user, Carbon::today()->copy()->subDays(5)->toDateString());
        // Simulate: reminded 20 days ago (older than the current due date + >7 days).
        $inv->forceFill(['reminded_at' => Carbon::now()->subDays(20), 'reminder_count' => 1])->saveQuietly();

        $this->artisan('invoices:remind')->assertOk();
        $inv->refresh();
        $this->assertSame(2, $inv->reminder_count);
    }

    public function test_remind_skips_not_yet_due(): void
    {
        $user = User::factory()->create();
        $inv = $this->open($user, Carbon::today()->copy()->addDays(5)->toDateString());

        $this->artisan('invoices:remind')->assertOk();
        $this->assertNull($inv->fresh()->reminded_at);
    }
}
