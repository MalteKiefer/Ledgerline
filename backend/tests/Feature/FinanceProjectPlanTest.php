<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FinanceProjectTask;
use App\Models\FinanceQuote;
use App\Models\FinanceTimeEntry;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceProjectPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quote_becomes_a_project_whose_tasks_carry_the_quoted_hours(): void
    {
        $this->signIn();
        $partner = $this->partner();
        $quote = $this->quote([
            'partner_id' => $partner->id,
            'title' => 'Netzwerk Neubau',
            'net' => 1459.8,
            'lines' => [
                ['desc' => "Beratung\nVor Ort", 'qty' => 8, 'unit' => 'Stunde', 'unitPrice' => 120, 'vatRate' => 19, 'kind' => 'service', 'productId' => null],
                ['desc' => 'Verkabelung', 'qty' => 4, 'unit' => 'Stunde', 'unitPrice' => 95, 'vatRate' => 19, 'kind' => 'service', 'productId' => null],
                // Hardware is delivered, not planned around — and it is already
                // on the invoice.
                ['desc' => 'Switch', 'qty' => 2, 'unit' => 'Stück', 'unitPrice' => 249.9, 'vatRate' => 19, 'kind' => 'hardware', 'productId' => null],
            ],
        ]);

        $res = $this->postJson(route('api.finance.quotes.project', $quote))->assertCreated();
        $res->assertJsonPath('project.name', 'Netzwerk Neubau')
            ->assertJsonPath('project.status', 'planned')
            ->assertJsonPath('project.partner_id', $partner->id)
            ->assertJsonPath('project.budget_net', '1459.80');

        $projectId = (int) $res->json('project.id');
        $tasks = FinanceProjectTask::query()->where('finance_project_id', $projectId)->orderBy('sort')->get();

        $this->assertCount(2, $tasks);
        // The first line of the description is the title, the rest the detail.
        $this->assertSame('Beratung', $tasks[0]->title);
        $this->assertSame('Vor Ort', $tasks[0]->description);
        // The quoted quantity IS the estimate.
        $this->assertSame('8.00', (string) $tasks[0]->estimate_hours);
        $this->assertSame('Verkabelung', $tasks[1]->title);

        // The quote remembers what it became, and a second click opens it.
        $this->assertSame($projectId, (int) $quote->fresh()?->converted_project_id);
        $this->postJson(route('api.finance.quotes.project', $quote))
            ->assertOk()
            ->assertJsonPath('already', true)
            ->assertJsonPath('project.id', $projectId);
        $this->assertSame(1, FinanceProject::query()->count());
    }

    public function test_the_plan_derives_its_figures_rather_than_storing_them(): void
    {
        $this->signIn();
        $project = $this->project();
        $a = $this->task($project, ['title' => 'A', 'estimate_hours' => 8]);
        $this->task($project, ['title' => 'B', 'estimate_hours' => 4, 'status' => 'done']);

        $this->postJson(route('api.finance.projects.time.store', $project), [
            'finance_project_task_id' => $a->id, 'hours' => 3, 'hourly_rate' => 100,
        ])->assertCreated();
        $this->postJson(route('api.finance.projects.time.store', $project), [
            'hours' => 2, 'hourly_rate' => 100, 'billable' => false,
        ])->assertCreated();

        $this->getJson(route('api.finance.projects.plan', $project))
            ->assertOk()
            ->assertJsonPath('totals.tasks', 2)
            ->assertJsonPath('totals.tasks_done', 1)
            ->assertJsonPath('totals.estimate_hours', 12)
            ->assertJsonPath('totals.worked_hours', 5)
            // The unbillable two hours count as worked but not as owed.
            ->assertJsonPath('totals.unbilled_hours', 3)
            ->assertJsonPath('totals.unbilled_value', 300);
    }

    public function test_the_rate_is_frozen_when_the_hour_is_logged(): void
    {
        // A rate change next year must not rewrite what last year's work was
        // worth.
        $this->signIn();
        $partner = $this->partner(['hourly_rate' => 120]);
        $project = $this->project(['partner_id' => $partner->id]);

        $entry = $this->postJson(route('api.finance.projects.time.store', $project), ['hours' => 2])->assertCreated();
        $this->assertSame('120.00', (string) $entry->json('entry.hourly_rate'));

        $partner->forceFill(['hourly_rate' => 150])->save();
        $this->assertSame('120.00', (string) FinanceTimeEntry::query()->firstOrFail()->hourly_rate);
    }

    public function test_billing_groups_by_rate_and_stamps_every_entry_it_took(): void
    {
        $this->signIn();
        $partner = $this->partner();
        $project = $this->project(['partner_id' => $partner->id]);

        // Two rates, several entries each — a customer wants "18.5 hours at 120",
        // not eleven identical lines.
        foreach ([[3, 120], [2.5, 120], [4, 95]] as [$hours, $rate]) {
            $this->postJson(route('api.finance.projects.time.store', $project), [
                'hours' => $hours, 'hourly_rate' => $rate,
            ])->assertCreated();
        }
        // Not billable, so it must stay behind.
        $kept = $this->postJson(route('api.finance.projects.time.store', $project), [
            'hours' => 1, 'hourly_rate' => 120, 'billable' => false,
        ])->json('entry.id');

        $res = $this->postJson(route('api.finance.projects.invoice-time', $project))->assertCreated();
        $res->assertJsonPath('invoice.status', 'draft')
            ->assertJsonPath('entries', 3)
            ->assertJsonPath('invoice.customer.partnerId', $partner->id);

        $lines = $res->json('invoice.lines');
        $this->assertCount(2, $lines);
        $byRate = collect($lines)->keyBy(fn (array $l): string => (string) $l['unitPrice']);
        $this->assertSame(4.0, (float) $byRate['95']['qty']);
        $this->assertSame(5.5, (float) $byRate['120']['qty']);
        // 4×95 + 5.5×120 = 1040
        $this->assertSame('1040.00', (string) $res->json('invoice.net'));

        $invoiceId = (int) $res->json('invoice.id');
        $this->assertSame(3, FinanceTimeEntry::query()->where('invoiced_finance_invoice_id', $invoiceId)->count());
        $this->assertNull(FinanceTimeEntry::query()->findOrFail($kept)->invoiced_finance_invoice_id);

        // Nothing left to bill: the same hour must not go out twice.
        $this->postJson(route('api.finance.projects.invoice-time', $project))
            ->assertStatus(422)
            ->assertJsonPath('error', 'nothing_to_invoice');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(1, DB::table('finance_invoices')->where('source_type', 'project_time_batch')->count());
    }

    public function test_an_invoiced_hour_can_no_longer_be_edited_or_deleted(): void
    {
        $this->signIn();
        $project = $this->project();
        $this->postJson(route('api.finance.projects.time.store', $project), ['hours' => 2, 'hourly_rate' => 100])->assertCreated();
        $this->postJson(route('api.finance.projects.invoice-time', $project))->assertCreated();

        $entry = FinanceTimeEntry::query()->firstOrFail();
        $this->putJson(route('api.finance.time-entries.update', $entry), ['hours' => 99])
            ->assertStatus(422)
            ->assertJsonPath('error', 'time_invoiced');
        $this->deleteJson(route('api.finance.time-entries.destroy', $entry))
            ->assertStatus(422)
            ->assertJsonPath('error', 'time_invoiced');

        $this->assertSame('2.00', (string) $entry->fresh()?->hours);
    }

    public function test_hours_can_be_billed_up_to_a_cut_off(): void
    {
        $this->signIn();
        $project = $this->project();
        $this->postJson(route('api.finance.projects.time.store', $project), ['hours' => 2, 'hourly_rate' => 100, 'date' => '2026-07-31'])->assertCreated();
        $this->postJson(route('api.finance.projects.time.store', $project), ['hours' => 3, 'hourly_rate' => 100, 'date' => '2026-08-15'])->assertCreated();

        $this->postJson(route('api.finance.projects.invoice-time', $project), ['until' => '2026-07-31'])
            ->assertCreated()
            ->assertJsonPath('entries', 1);

        // The August hours are still waiting.
        $this->assertSame(1, FinanceTimeEntry::query()->whereNull('invoiced_invoice_id')->whereNull('invoiced_finance_invoice_id')->count());
    }

    public function test_deleting_a_task_keeps_the_hours_that_were_worked_on_it(): void
    {
        $this->signIn();
        $project = $this->project();
        $task = $this->task($project, ['title' => 'Weg damit']);
        $this->postJson(route('api.finance.projects.time.store', $project), [
            'finance_project_task_id' => $task->id, 'hours' => 2,
        ])->assertCreated();

        $this->deleteJson(route('api.finance.project-tasks.destroy', $task))->assertOk();

        $entry = FinanceTimeEntry::query()->firstOrFail();
        $this->assertSame('2.00', (string) $entry->hours);
        $this->assertNull($entry->finance_project_task_id);
    }

    public function test_reordering_cannot_pull_in_a_task_from_another_project(): void
    {
        $this->signIn();
        $mine = $this->project(['name' => 'Meins']);
        $other = $this->project(['name' => 'Anderes']);
        $a = $this->task($mine, ['title' => 'A', 'sort' => 1]);
        $foreign = $this->task($other, ['title' => 'Fremd', 'sort' => 7]);

        $this->postJson(route('api.finance.projects.tasks.reorder', $mine), ['ids' => [$foreign->id, $a->id]])->assertOk();

        $this->assertSame(7, (int) $foreign->fresh()?->sort);
        $this->assertSame(2, (int) $a->fresh()?->sort);
    }

    public function test_a_task_from_another_owner_cannot_be_reached(): void
    {
        $this->signIn();
        $project = $this->project();
        $task = $this->task($project, ['title' => 'Mein']);

        app('auth')->forgetGuards();
        $this->signIn(User::factory()->create());

        $this->getJson(route('api.finance.projects.plan', $project))->assertNotFound();
        $this->putJson(route('api.finance.project-tasks.update', $task), ['title' => 'Stolen'])->assertNotFound();
        $this->postJson(route('api.finance.projects.time.store', $project), ['hours' => 1])->assertNotFound();
        $this->postJson(route('api.finance.projects.invoice-time', $project))->assertNotFound();

        $this->assertSame('Mein', (string) $task->fresh()?->title);
    }

    public function test_hours_cannot_be_booked_on_another_projects_task(): void
    {
        $this->signIn();
        $a = $this->project(['name' => 'A']);
        $b = $this->project(['name' => 'B']);
        $taskOfB = $this->task($b, ['title' => 'B-Aufgabe']);

        $this->postJson(route('api.finance.projects.time.store', $a), [
            'finance_project_task_id' => $taskOfB->id, 'hours' => 1,
        ])->assertStatus(422);
    }

    public function test_an_overdue_task_stops_being_overdue_once_it_is_done(): void
    {
        // Red on work already delivered is noise, and noise is what makes a
        // warning ignorable.
        $this->signIn();
        $project = $this->project();
        $task = $this->task($project, ['title' => 'Spät', 'due_on' => now()->subWeek()->toDateString()]);
        $this->assertTrue($task->fresh()?->isOverdue());

        $this->putJson(route('api.finance.project-tasks.update', $task), ['status' => 'done'])->assertOk();
        $this->assertFalse($task->fresh()?->isOverdue());
    }

    /** @param array<string, mixed> $attrs */
    private function partner(array $attrs = []): FinancePartner
    {
        $partner = new FinancePartner;
        $partner->fill(array_merge(['name' => 'IntellyTec GmbH', 'kind' => 'customer'], $attrs));
        $partner->save();

        return $partner;
    }

    /** @param array<string, mixed> $attrs */
    private function project(array $attrs = []): FinanceProject
    {
        $project = new FinanceProject;
        $project->fill(array_merge(['name' => 'Projekt', 'kind' => 'business'], $attrs));
        $project->save();

        return $project;
    }

    /** @param array<string, mixed> $attrs */
    private function task(FinanceProject $project, array $attrs): FinanceProjectTask
    {
        $task = new FinanceProjectTask;
        $task->fill(array_merge([
            'finance_project_id' => $project->id,
            'title' => 'Aufgabe',
            'status' => 'open',
        ], $attrs));
        $task->save();

        return $task;
    }

    /** @param array<string, mixed> $attrs */
    private function quote(array $attrs): FinanceQuote
    {
        $quote = new FinanceQuote;
        $quote->fill(array_merge([
            'title' => 'Angebot',
            'issue_date' => '2026-05-04',
            'customer' => ['name' => 'Kunde'],
        ], $attrs));
        $quote->save();

        return $quote;
    }
}
