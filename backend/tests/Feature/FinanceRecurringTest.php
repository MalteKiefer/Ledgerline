<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\FinanceRecurring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subscription detection. The two failure modes that matter are opposite: a
 * missed subscription costs nothing but a missed insight, while a false one
 * ("you have a subscription at the bakery") destroys trust in the whole card —
 * so the tests pin both the finding and the refusing.
 */
class FinanceRecurringTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $scope = 'business'): PaymentMethod
    {
        return PaymentMethod::create(['type' => 'bank', 'name' => 'Konto', 'scope' => $scope]);
    }

    /** @param list<array{0: string, 1: float}> $charges */
    private function charge(PaymentMethod $pm, string $who, array $charges): void
    {
        foreach ($charges as [$date, $amount]) {
            BankTransaction::create([
                'payment_method_id' => $pm->id, 'date' => $date,
                'amount' => -$amount, 'counterparty' => $who,
            ]);
        }
    }

    public function test_a_monthly_charge_is_found_and_annualised(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        // Relative to today: a "current" subscription pinned to fixed dates would
        // read as stale the moment the calendar moves past it.
        $this->charge($pm, 'NETCUP GmbH', [
            [now()->subDays(93)->toDateString(), 9.99],
            [now()->subDays(62)->toDateString(), 9.99],
            [now()->subDays(31)->toDateString(), 9.99],
            [now()->toDateString(), 9.99],
        ]);

        $found = app(FinanceRecurring::class)->detect();

        $this->assertCount(1, $found);
        $this->assertSame('monthly', $found[0]['cadence']);
        $this->assertSame(9.99, $found[0]['amount']);
        // ~12 charges a year; the point of the figure is comparability, not cents.
        $this->assertGreaterThan(115.0, $found[0]['yearly']);
        $this->assertLessThan(125.0, $found[0]['yearly']);
        $this->assertSame(4, $found[0]['charges']);
        $this->assertFalse($found[0]['stale']);
    }

    public function test_a_price_increase_is_reported(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        // The thing nobody notices on a statement.
        $this->charge($pm, 'Streaming', [
            [now()->subDays(62)->toDateString(), 9.99],
            [now()->subDays(31)->toDateString(), 9.99],
            [now()->toDateString(), 12.99],
        ]);

        $found = app(FinanceRecurring::class)->detect();

        $this->assertNotNull($found[0]['price_change']);
        $this->assertSame(9.99, $found[0]['price_change']['from']);
        $this->assertSame(12.99, $found[0]['price_change']['to']);
        $this->assertSame(12.99, $found[0]['amount'], 'the current price is the new one');
    }

    public function test_a_subscription_that_stopped_coming_is_flagged(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        // Monthly, but the last charge is long past due: cancelled, or silently
        // stopped. Both are worth seeing.
        $this->charge($pm, 'Altdienst', [
            ['2024-01-10', 5.0], ['2024-02-10', 5.0], ['2024-03-10', 5.0],
        ]);

        $found = app(FinanceRecurring::class)->detect();

        $this->assertTrue($found[0]['stale']);
    }

    public function test_an_annual_charge_is_found(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        $this->charge($pm, 'Domain Registrar', [
            [now()->subDays(730)->toDateString(), 29.0],
            [now()->subDays(365)->toDateString(), 29.0],
            [now()->toDateString(), 32.0],
        ]);

        $found = app(FinanceRecurring::class)->detect();

        $this->assertSame('annual', $found[0]['cadence']);
        $this->assertSame(32.0, $found[0]['yearly'], 'an annual charge annualises to itself');
    }

    public function test_scattered_payments_to_the_same_shop_are_not_a_subscription(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        // Same merchant, no rhythm and varying amounts — a shop, not a service.
        $this->charge($pm, 'Bäckerei', [
            ['2026-03-02', 4.20], ['2026-03-19', 11.50], ['2026-05-27', 2.80], ['2026-06-01', 7.10],
        ]);

        $this->assertSame([], app(FinanceRecurring::class)->detect());
    }

    public function test_two_charges_are_not_enough(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        $this->charge($pm, 'Irgendwas', [['2026-05-01', 10.0], ['2026-06-01', 10.0]]);

        $this->assertSame([], app(FinanceRecurring::class)->detect());
    }

    public function test_the_legal_form_does_not_split_one_merchant_in_two(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->account();
        $this->charge($pm, 'NETCUP GmbH', [
            [now()->subDays(93)->toDateString(), 9.99], [now()->subDays(62)->toDateString(), 9.99],
        ]);
        $this->charge($pm, 'netcup Deutschland', [
            [now()->subDays(31)->toDateString(), 9.99], [now()->toDateString(), 9.99],
        ]);

        $found = app(FinanceRecurring::class)->detect();

        $this->assertCount(1, $found, 'one service, not two');
        $this->assertSame(4, $found[0]['charges']);
    }

    public function test_each_group_states_its_scope_and_the_endpoint_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $pm = $this->account('private');
        $this->charge($pm, 'Fitness', [
            [now()->subDays(62)->toDateString(), 30.0],
            [now()->subDays(31)->toDateString(), 30.0],
            [now()->toDateString(), 30.0],
        ]);

        $res = $this->getJson(route('api.finance.recurring'))->assertOk();
        $this->assertSame('private', $res->json('recurring.0.scope'));

        // Another user sees nothing of it. Real bearer tokens rather than a second
        // actingAs: the sanctum guard caches its resolution per request cycle, so
        // switching identity that way is unreliable (it either keeps the first user
        // or, after forgetGuards, no user at all).
        app('auth')->forgetGuards();
        $stranger = User::factory()->create()->createToken('t', ['device'])->plainTextToken;
        $this->getJson(route('api.finance.recurring'), ['Authorization' => 'Bearer '.$stranger])
            ->assertOk()
            ->assertExactJson(['recurring' => []]);
    }
}
