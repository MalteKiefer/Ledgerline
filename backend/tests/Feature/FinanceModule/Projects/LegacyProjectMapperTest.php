<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\BankTransaction;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FinanceProjectTask;
use App\Models\FinanceQuote;
use App\Models\FinanceReceipt;
use App\Models\FinanceTimeEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyProjectMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_every_legacy_status_and_kind_with_no_diagnostics(): void
    {
        $this->signIn();
        $mapper = new LegacyProjectMapper;

        foreach (['planned', 'active', 'on_hold', 'done', 'cancelled'] as $status) {
            foreach (['business', 'private'] as $kind) {
                $project = $this->project(['name' => "{$status}-{$kind}", 'status' => $status, 'kind' => $kind]);
                $result = $mapper->map($project);

                $this->assertSame($status, $result['project']['status']);
                $this->assertSame($kind, $result['project']['kind']);
                $this->assertSame('legacy.finance_project', $result['source_type']);
                $this->assertSame($project->id, $result['source_id']);
                $this->assertFalse(LegacyProjectMapper::isBlocking($result));
            }
        }
    }

    public function test_maps_root_and_subproject_hierarchy_and_archive_state(): void
    {
        $this->signIn();
        $mapper = new LegacyProjectMapper;
        $root = $this->project(['name' => 'Root']);
        $child = $this->project(['name' => 'Child', 'parent_id' => $root->id]);
        $child->delete();
        $child->refresh();

        $rootResult = $mapper->map($root);
        $childResult = $mapper->map($child);

        $this->assertNull($rootResult['project']['parent_source_id']);
        $this->assertSame($root->id, $childResult['project']['parent_source_id']);
        $this->assertTrue($childResult['project']['archived']);
        $this->assertFalse($rootResult['project']['archived']);
    }

    public function test_maps_partner_and_quote_references_with_an_unresolved_quote_diagnostic(): void
    {
        $this->signIn();
        $partner = FinancePartner::create(['name' => 'Ada GmbH']);
        $quote = $this->quote(['title' => 'Legacy quote']);
        $project = $this->project(['partner_id' => $partner->id]);
        DB::table('finance_projects')->where('id', $project->id)->update(['quote_id' => $quote->id]);
        $project->refresh();

        $result = (new LegacyProjectMapper)->map($project);

        $this->assertSame("legacy-partner:{$partner->id}", $result['project']['partner_reference']);
        $this->assertSame("legacy-quote-unresolved:{$quote->id}", $result['project']['quote_reference']);
        $this->assertFalse(LegacyProjectMapper::isBlocking($result));
        $codes = array_map(static fn ($d) => $d->code, $result['diagnostics']);
        $this->assertContains('project_quote_unresolved', $codes);
    }

    public function test_maps_exact_budget_and_flags_negative_and_out_of_range_budgets(): void
    {
        $this->signIn();
        $mapper = new LegacyProjectMapper;

        $none = $mapper->map($this->project(['budget_net' => null]));
        $this->assertNull($none['project']['budget_minor']);

        $exact = $mapper->map($this->project(['budget_net' => '1234.56']));
        $this->assertSame(123456, $exact['project']['budget_minor']);

        $negative = $mapper->map($this->project(['budget_net' => '-500.00']));
        $this->assertSame(-50000, $negative['project']['budget_minor']);
    }

    public function test_maps_the_mutable_note_once_into_an_initial_internal_project_note(): void
    {
        $this->signIn();
        $withNote = $mapper = (new LegacyProjectMapper)->map($this->project(['note' => 'Handle with care']));
        $this->assertSame(['type' => 'note', 'visibility' => 'internal', 'body' => 'Handle with care'], $withNote['note']);

        $blank = (new LegacyProjectMapper)->map($this->project(['note' => '   ']));
        $this->assertNull($blank['note']);
    }

    public function test_maps_tasks_including_milestones_and_flags_a_milestone_with_an_estimate(): void
    {
        $this->signIn();
        $project = $this->project();
        $task = FinanceProjectTask::create([
            'finance_project_id' => $project->id, 'title' => 'Site survey', 'status' => 'in_progress',
            'estimate_hours' => '2.50', 'sort' => 1,
        ]);
        $milestone = FinanceProjectTask::create([
            'finance_project_id' => $project->id, 'title' => 'Handover', 'status' => 'open',
            'is_milestone' => true, 'estimate_hours' => '1.00', 'sort' => 2,
        ]);

        $result = (new LegacyProjectMapper)->map($project);

        $mapped = collect($result['work_items'])->keyBy('source_id');
        $this->assertSame(25000, $mapped[$task->id]['estimate_quantity_scaled']);
        $this->assertSame('in_progress', $mapped[$task->id]['status']);
        $this->assertNull($mapped[$milestone->id]['estimate_quantity_scaled']);
        $this->assertTrue(LegacyProjectMapper::isBlocking($result));
        $codes = array_map(static fn ($d) => $d->code, $result['diagnostics']);
        $this->assertContains('task_milestone_with_estimate', $codes);
    }

    public function test_maps_time_entries_with_negative_corrections_frozen_rate_and_invoiced_lock(): void
    {
        $this->signIn();
        $project = $this->project();
        $normal = FinanceTimeEntry::create([
            'finance_project_id' => $project->id, 'date' => '2026-08-20', 'hours' => '3.25',
            'billable' => true, 'hourly_rate' => '95.00',
        ]);
        $correction = FinanceTimeEntry::create([
            'finance_project_id' => $project->id, 'date' => '2026-08-21', 'hours' => '-0.50',
            'billable' => true, 'hourly_rate' => '95.00',
        ]);
        $missingRate = FinanceTimeEntry::create([
            'finance_project_id' => $project->id, 'date' => '2026-08-22', 'hours' => '1.00', 'billable' => false,
        ]);
        $invoicedId = (int) $this->postJson(route('api.finance.projects.time.store', $project), [
            'hours' => 4, 'hourly_rate' => 95, 'date' => '2026-08-19',
        ])->assertCreated()->json('entry.id');
        $invoiceResponse = $this->postJson(route('api.finance.projects.invoice-time', $project))->assertCreated();
        $invoiceId = (int) $invoiceResponse->json('invoice.id');
        $invoiced = FinanceTimeEntry::query()->findOrFail($invoicedId);

        $result = (new LegacyProjectMapper)->map($project);
        $mapped = collect($result['time_entries'])->keyBy('source_id');

        $this->assertSame(32500, $mapped[$normal->id]['quantity_scaled']);
        $this->assertSame(9500, $mapped[$normal->id]['hourly_rate_minor']);
        $this->assertSame(-5000, $mapped[$correction->id]['quantity_scaled']);
        $this->assertNull($mapped[$missingRate->id]['hourly_rate_minor']);
        $this->assertSame("legacy-invoice:{$invoiceId}", $mapped[$invoiced->id]['invoice_target_reference']);
        $this->assertTrue($mapped[$invoiced->id]['invoiced']);
        $this->assertFalse(LegacyProjectMapper::isBlocking($result));
        $codes = array_map(static fn ($d) => $d->code, $result['diagnostics']);
        $this->assertContains('time_entry_rate_missing', $codes);
        $this->assertContains('time_entry_invoice_unresolved', $codes);
    }

    public function test_rejects_a_zero_hours_time_entry_as_blocking(): void
    {
        $this->signIn();
        $project = $this->project();
        DB::table('finance_time_entries')->insert([
            'user_id' => $project->user_id, 'finance_project_id' => $project->id,
            'date' => '2026-08-20', 'hours' => '0.00', 'billable' => true, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new LegacyProjectMapper)->map($project);

        $this->assertTrue(LegacyProjectMapper::isBlocking($result));
    }

    public function test_maps_valid_expense_rows_with_numeric_and_string_decimals_and_infers_direction(): void
    {
        $this->signIn();
        $project = $this->project();
        $json = '[{"amount":"120.50","title":"Materials"},{"amount":45,"direction":"in","title":"Refund"},{"amount":"-10.00","note":"Correction"}]';
        DB::table('finance_projects')->where('id', $project->id)->update(['expenses' => $json]);
        $project->refresh();

        $result = (new LegacyProjectMapper)->map($project);

        $this->assertFalse(LegacyProjectMapper::isBlocking($result));
        $this->assertCount(3, $result['ledger_entries']);
        $this->assertSame(['out', 12050, 'Materials'], [$result['ledger_entries'][0]['direction'], $result['ledger_entries'][0]['amount_minor'], $result['ledger_entries'][0]['title']]);
        $this->assertSame(['in', 4500], [$result['ledger_entries'][1]['direction'], $result['ledger_entries'][1]['amount_minor']]);
        $this->assertSame(['in', 1000], [$result['ledger_entries'][2]['direction'], $result['ledger_entries'][2]['amount_minor']]);
    }

    public function test_retains_unknown_expense_keys_under_legacy_metadata(): void
    {
        $this->signIn();
        $project = $this->project();
        $json = '[{"amount":"10.00","title":"Cable","account":"cash"}]';
        DB::table('finance_projects')->where('id', $project->id)->update(['expenses' => $json]);
        $project->refresh();

        $result = (new LegacyProjectMapper)->map($project);

        $this->assertSame(['account' => 'cash'], $result['ledger_entries'][0]['legacy_metadata']);
    }

    public function test_rejects_malformed_expense_json_exponent_and_scale_as_blocking(): void
    {
        $this->signIn();
        $mapper = new LegacyProjectMapper;
        $cases = [
            'not json at all',
            '{"amount": "10.00"}', // not a top-level array
            '[{"amount": "10.001"}]', // more than two fraction digits
            '[{"amount": 1.5e3}]', // exponent notation
            '[{"title": "No amount"}]', // amount missing entirely
        ];

        foreach ($cases as $json) {
            $project = $this->project();
            DB::table('finance_projects')->where('id', $project->id)->update(['expenses' => $json]);
            $project->refresh();

            $result = $mapper->map($project);

            $this->assertTrue(LegacyProjectMapper::isBlocking($result), "Expected a blocking diagnostic for: {$json}");
        }
    }

    public function test_maps_receipt_and_transaction_evidence_and_flags_cross_owner_pointers(): void
    {
        $this->signIn();
        $project = $this->project();
        $paymentMethod = PaymentMethod::create(['type' => 'bank', 'name' => 'Main']);
        $transaction = BankTransaction::create([
            'payment_method_id' => $paymentMethod->id, 'date' => '2026-08-20', 'amount' => '-10.00',
            'finance_project_id' => $project->id,
        ]);
        $receipt = new FinanceReceipt;
        $receipt->fill(['name' => 'receipt.pdf', 'finance_project_id' => $project->id, 'amount' => '10.00']);
        $receipt->blob_path = 'invoices/receipt.pdf';
        $receipt->save();

        $otherOwner = User::factory()->create();
        DB::table('finance_receipts')->where('id', $receipt->id)->update(['user_id' => $otherOwner->id]);

        $result = (new LegacyProjectMapper)->map($project);

        $kinds = array_map(static fn ($link) => $link['source_type'], $result['document_links']);
        $this->assertContains('bank_transaction', $kinds);
        $this->assertNotContains($receipt->id, array_column($result['document_links'], 'source_reference'));
        $this->assertTrue(LegacyProjectMapper::isBlocking($result));
        $codes = array_map(static fn ($d) => $d->code, $result['diagnostics']);
        $this->assertContains('document_link_cross_owner', $codes);
    }

    public function test_mapping_the_same_project_twice_is_deterministic(): void
    {
        $this->signIn();
        $project = $this->project(['note' => 'Stable']);
        FinanceProjectTask::create(['finance_project_id' => $project->id, 'title' => 'A', 'status' => 'open', 'sort' => 1]);
        $mapper = new LegacyProjectMapper;

        $first = $mapper->map($project);
        $second = $mapper->map($project);
        unset($first['diagnostics'], $second['diagnostics']);

        $this->assertSame($first, $second);
    }

    /** @param array<string, mixed> $attributes */
    private function project(array $attributes = []): FinanceProject
    {
        return FinanceProject::create(array_merge([
            'name' => 'Legacy project', 'kind' => 'business', 'status' => 'planned',
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function quote(array $attributes = []): FinanceQuote
    {
        $quote = new FinanceQuote;
        $quote->fill(array_merge([
            'title' => 'Legacy quote', 'issue_date' => '2026-08-28',
            'customer' => ['name' => 'Customer'], 'lines' => [],
            'net' => 0, 'vat' => 0, 'gross' => 0,
        ], $attributes));
        $quote->save();

        return $quote;
    }
}
