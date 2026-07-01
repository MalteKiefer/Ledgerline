<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-06-01',
            'description' => 'Consulting',
            'hours' => '2',
            'currency' => 'EUR',
            'billable' => '1',
        ], $overrides);
    }

    public function test_guests_cannot_access_time(): void
    {
        $this->get(route('finance.time-entries.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_entries_with_totals(): void
    {
        $this->signIn();
        TimeEntry::factory()->create(['description' => 'Findable work', 'minutes' => 60, 'rate_cents' => 9000, 'currency' => 'EUR']);

        $this->get(route('finance.time-entries.index'))
            ->assertOk()
            ->assertSee('Findable work')
            ->assertSee('Billable (EUR)');
    }

    public function test_store_converts_hours_and_uses_explicit_rate(): void
    {
        $this->signIn();

        $this->post(route('finance.time-entries.store'), $this->payload(['description' => 'Rollout', 'rate' => '90']))
            ->assertRedirect(route('finance.time-entries.index'));

        $entry = TimeEntry::firstWhere('description', 'Rollout');
        $this->assertSame(120, $entry->minutes);
        $this->assertSame(9000, $entry->rate_cents);
        $this->assertSame(18000, $entry->amount()->cents);
    }

    public function test_rate_falls_back_to_customer_default(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['default_rate_cents' => 8000]);

        $this->post(route('finance.time-entries.store'), $this->payload(['description' => 'CustRate', 'customer_id' => $customer->id]));

        $this->assertSame(8000, TimeEntry::firstWhere('description', 'CustRate')->rate_cents);
    }

    public function test_project_rate_overrides_customer_default(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['default_rate_cents' => 8000]);
        $project = Project::factory()->for($customer)->create(['default_rate_cents' => 10000]);

        $this->post(route('finance.time-entries.store'), $this->payload([
            'description' => 'ProjRate',
            'customer_id' => $customer->id,
            'project_id' => $project->id,
        ]));

        $this->assertSame(10000, TimeEntry::firstWhere('description', 'ProjRate')->rate_cents);
    }

    public function test_update_and_destroy(): void
    {
        $this->signIn();
        $entry = TimeEntry::factory()->create(['description' => 'Old']);

        $this->put(route('finance.time-entries.update', $entry), $this->payload(['description' => 'New', 'rate' => '80']))
            ->assertRedirect(route('finance.time-entries.index'));
        $this->assertSame('New', $entry->fresh()->description);

        $this->delete(route('finance.time-entries.destroy', $entry))->assertRedirect();
        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
    }

    public function test_customer_default_rate_is_saved(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'default_rate' => '120.00',
        ])->assertRedirect();

        $this->assertSame(12000, $customer->fresh()->default_rate_cents);
    }
}
