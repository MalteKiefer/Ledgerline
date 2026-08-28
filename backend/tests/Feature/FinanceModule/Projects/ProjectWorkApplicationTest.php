<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\FinanceCategory;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\CreateInvoiceDraftFromTime;
use App\Modules\Finance\Application\Commands\Projects\CreateLedgerEntry;
use App\Modules\Finance\Application\Commands\Projects\CreateWorkItem;
use App\Modules\Finance\Application\Commands\Projects\DeleteLedgerEntry;
use App\Modules\Finance\Application\Commands\Projects\DeleteProjectTime;
use App\Modules\Finance\Application\Commands\Projects\DeleteWorkItem;
use App\Modules\Finance\Application\Commands\Projects\LogProjectTime;
use App\Modules\Finance\Application\Commands\Projects\ReorderWorkItems;
use App\Modules\Finance\Application\Commands\Projects\UpdateLedgerEntry;
use App\Modules\Finance\Application\Commands\Projects\UpdateProjectTime;
use App\Modules\Finance\Application\Commands\Projects\UpdateWorkItem;
use App\Modules\Finance\Application\DTOs\Projects\CreateLedgerEntryData;
use App\Modules\Finance\Application\DTOs\Projects\CreateWorkItemData;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeData;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeLine;
use App\Modules\Finance\Application\DTOs\Projects\LogTimeData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectFinancialSourceRow;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\DTOs\Projects\UpdateTimeData;
use App\Modules\Finance\Application\DTOs\Projects\UpdateWorkItemData;
use App\Modules\Finance\Application\Ports\Projects\ProjectFinancialSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectRateSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Queries\Projects\GetProjectTotals;
use App\Modules\Finance\Application\Queries\Projects\ListProjectLedger;
use App\Modules\Finance\Application\Queries\Projects\ListProjectWork;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDraftFromTimeAdapter;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectFinancialSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectRateSource;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectWorkRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class ProjectWorkApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_work_ports_have_production_bindings(): void
    {
        $this->assertInstanceOf(EloquentProjectWorkRepository::class, app(ProjectWorkRepository::class));
        $this->assertInstanceOf(LegacyProjectRateSource::class, app(ProjectRateSource::class));
        $this->assertInstanceOf(LegacyProjectFinancialSource::class, app(ProjectFinancialSource::class));
        $this->assertInstanceOf(LegacyInvoiceDraftFromTimeAdapter::class, app(ProjectToInvoicePort::class));
    }

    public function test_work_items_enforce_exact_values_owner_workflow_and_explicit_cas(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 10:00:00');

        $created = app(CreateWorkItem::class)->handle(new CreateWorkItemData(
            $project['id'], '  Build API  ', (int) $owner->id, $at,
            description: 'Exact work',
            startsOn: new DateTimeImmutable('2026-09-01'),
            dueOn: new DateTimeImmutable('2026-09-30'),
            estimateHours: '1.2500',
        ));

        $this->assertSame('Build API', $created->title);
        $this->assertSame(12_500, $created->estimateQuantityScaled);
        $this->assertSame(WorkItemStatus::Open, $created->status);
        $this->assertSame(0, $created->version);

        $updated = app(UpdateWorkItem::class)->handle(new UpdateWorkItemData(
            $project['id'], $created->uuid, 0, 'Build stable API', WorkItemStatus::InProgress,
            (int) $owner->id, $at, estimateHours: '2.5',
        ));
        $this->assertSame(WorkItemStatus::InProgress, $updated->status);
        $this->assertSame(25_000, $updated->estimateQuantityScaled);
        $this->assertSame(1, $updated->version);

        try {
            app(UpdateWorkItem::class)->handle(new UpdateWorkItemData(
                $project['id'], $created->uuid, 0, 'Stale', WorkItemStatus::Done,
                (int) $owner->id, $at,
            ));
            $this->fail('A stale work item update was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('version_conflict', $exception->getMessage());
        }

        foreach ([
            new CreateWorkItemData($project['id'], 'Milestone', (int) $owner->id, $at, estimateHours: '1', isMilestone: true),
            new CreateWorkItemData($project['id'], 'Wrong actor', (int) $foreign->id, $at),
            new CreateWorkItemData($project['id'], 'Bad dates', (int) $owner->id, $at, startsOn: new DateTimeImmutable('2026-10-02'), dueOn: new DateTimeImmutable('2026-10-01')),
        ] as $invalid) {
            try {
                app(CreateWorkItem::class)->handle($invalid);
                $this->fail('Invalid work item input was accepted.');
            } catch (InvalidProjectAction|InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(
            ['work_item.created', 'work_item.updated'],
            DB::table('finance_project_activities')->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_reorder_requires_the_exact_live_set_pages_stably_and_delete_detaches_only_uninvoiced_time(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 11:00:00');
        $command = app(CreateWorkItem::class);
        $first = $command->handle(new CreateWorkItemData($project['id'], 'First', (int) $owner->id, $at));
        $second = $command->handle(new CreateWorkItemData($project['id'], 'Second', (int) $owner->id, $at));
        $third = $command->handle(new CreateWorkItemData($project['id'], 'Third', (int) $owner->id, $at));

        try {
            app(ReorderWorkItems::class)->handle($project['id'], [$second->uuid, $first->uuid], (int) $owner->id, $at);
            $this->fail('An incomplete reorder was accepted.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('work_item_set_mismatch', $exception->errorCode);
        }

        $ordered = app(ReorderWorkItems::class)->handle(
            $project['id'], [$third->uuid, $first->uuid, $second->uuid], (int) $owner->id, $at,
        );
        $this->assertSame([$third->uuid, $first->uuid, $second->uuid], array_column($ordered, 'uuid'));
        $this->assertSame(1, $ordered[1]->version);

        $workItemId = (int) DB::table('finance_project_work_items')->where('uuid', $first->uuid)->value('id');
        $projectId = $project['record_id'];
        $now = '2026-08-28 11:00:00';
        DB::table('finance_project_time_entries')->insert([
            [
                'user_id' => $owner->id, 'project_id' => $projectId, 'work_item_id' => $workItemId,
                'uuid' => (string) Str::uuid(), 'worked_on' => '2026-08-28', 'quantity_scaled' => 10_000,
                'description' => null, 'billable' => true, 'hourly_rate_minor' => 10_000, 'currency' => 'EUR',
                'invoice_target_reference' => null, 'invoiced_at' => null, 'version' => 0,
                'created_by' => $owner->id, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'user_id' => $owner->id, 'project_id' => $projectId, 'work_item_id' => $workItemId,
                'uuid' => (string) Str::uuid(), 'worked_on' => '2026-08-28', 'quantity_scaled' => 10_000,
                'description' => null, 'billable' => true, 'hourly_rate_minor' => 10_000, 'currency' => 'EUR',
                'invoice_target_reference' => 'legacy-invoice:1', 'invoiced_at' => $now, 'version' => 0,
                'created_by' => $owner->id, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        app(DeleteWorkItem::class)->handle($project['id'], $first->uuid, $ordered[1]->version, (int) $owner->id, $at);
        $links = DB::table('finance_project_time_entries')->where('work_item_id', $workItemId)->orWhereNull('work_item_id')->orderBy('id')->pluck('work_item_id')->all();
        $this->assertSame([null, $workItemId], $links);
        $this->assertNotNull(DB::table('finance_project_work_items')->where('id', $workItemId)->value('deleted_at'));

        $bulk = [];
        for ($index = 0; $index < 101; $index++) {
            $bulk[] = [
                'user_id' => $owner->id, 'project_id' => $projectId, 'uuid' => (string) Str::uuid(),
                'title' => 'Bulk '.$index, 'description' => null, 'status' => 'open', 'starts_on' => null,
                'due_on' => null, 'estimate_quantity_scaled' => null, 'is_milestone' => false,
                'sort' => 100 + $index, 'source_revision_id' => null, 'source_line_index' => null,
                'product_reference' => null, 'version' => 0, 'created_by' => $owner->id,
                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('finance_project_work_items')->insert($bulk);

        $page = app(ListProjectWork::class)->handle($project['id'], 'work_items', 1, 500);
        $this->assertCount(100, $page['items']);
        $this->assertSame(100, $page['per_page']);
        $this->assertSame(103, $page['total']);
        $this->assertSame($third->uuid, $page['items'][0]->uuid);
        $this->assertSame($second->uuid, $page['items'][1]->uuid);
    }

    public function test_time_entries_use_exact_decimal_hours_freeze_rates_and_reject_invoiced_mutations(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner, ['partner_reference' => 'legacy-partner:99']);
        $at = new DateTimeImmutable('2026-08-28 12:00:00');
        $workItem = app(CreateWorkItem::class)->handle(new CreateWorkItemData(
            $project['id'], 'Tracked work', (int) $owner->id, $at,
        ));
        $rates = new ProjectRateStub(Money::fromMinor(12_500, 'EUR'));
        $this->app->instance(ProjectRateSource::class, $rates);

        $entry = app(LogProjectTime::class)->handle(new LogTimeData(
            $project['id'], $workItem->uuid, new DateTimeImmutable('2026-08-28'),
            '1.2345', (int) $owner->id, $at, description: 'Exact time', currency: 'eur',
        ));
        $this->assertSame(12_345, $entry->quantityScaled);
        $this->assertSame(12_500, $entry->hourlyRateMinor);
        $this->assertSame('EUR', $entry->currency);

        $rates->rate = Money::fromMinor(99_999, 'EUR');
        $persisted = app(ProjectWorkRepository::class)->timeEntry($project['id'], $entry->uuid);
        $this->assertSame(12_500, $persisted->hourlyRateMinor);

        $corrected = app(LogProjectTime::class)->handle(new LogTimeData(
            $project['id'], null, new DateTimeImmutable('2026-08-29'),
            '-0.5000', (int) $owner->id, $at,
            hourlyRate: Money::fromMinor(10_000, 'EUR'), currency: 'EUR',
        ));
        $this->assertSame(-5_000, $corrected->quantityScaled);

        try {
            new LogTimeData($project['id'], null, new DateTimeImmutable('2026-08-28'), 1.5, (int) $owner->id, $at);
            $this->fail('A JSON float was accepted as authoritative hours.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('time_quantity_must_be_decimal_string', $exception->getMessage());
        }

        try {
            app(LogProjectTime::class)->handle(new LogTimeData(
                $project['id'], null, new DateTimeImmutable('2026-08-28'), '0', (int) $owner->id, $at,
            ));
            $this->fail('Zero time was accepted.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('time_quantity_nonzero', $exception->errorCode);
        }

        $updated = app(UpdateProjectTime::class)->handle(new UpdateTimeData(
            $project['id'], $entry->uuid, 0, null, new DateTimeImmutable('2026-08-30'),
            '2.0000', (int) $owner->id, $at, description: 'Moved off task', currency: 'EUR',
        ));
        $this->assertSame(20_000, $updated->quantityScaled);
        $this->assertSame(12_500, $updated->hourlyRateMinor);
        $this->assertNull($updated->workItemUuid);

        DB::table('finance_project_time_entries')->where('uuid', $entry->uuid)->update([
            'invoice_target_reference' => 'legacy-invoice:42',
            'invoiced_at' => '2026-08-28 13:00:00',
        ]);
        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    app(UpdateProjectTime::class)->handle(new UpdateTimeData(
                        $project['id'], $entry->uuid, 1, $workItem->uuid, new DateTimeImmutable('2026-08-30'),
                        '3', (int) $owner->id, $at, currency: 'EUR',
                    ));
                } else {
                    app(DeleteProjectTime::class)->handle($project['id'], $entry->uuid, 1, (int) $owner->id, $at);
                }
                $this->fail("Invoiced time {$operation} was accepted.");
            } catch (InvalidProjectAction $exception) {
                $this->assertSame('time_entry_invoiced', $exception->errorCode);
            }
        }

        $page = app(ListProjectWork::class)->handle($project['id'], 'time_entries', 1, 500);
        $this->assertSame(100, $page['per_page']);
        $this->assertSame(2, $page['total']);
        foreach (array_column($page['items'], 'quantityScaled') as $quantityScaled) {
            $this->assertIsInt($quantityScaled);
        }
    }

    public function test_billable_time_without_an_explicit_or_partner_rate_is_rejected_without_writes(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $this->app->instance(ProjectRateSource::class, new ProjectRateStub(null));

        try {
            app(LogProjectTime::class)->handle(new LogTimeData(
                $project['id'], null, new DateTimeImmutable('2026-08-28'),
                '1', (int) $owner->id, new DateTimeImmutable('2026-08-28 12:00:00'),
            ));
            $this->fail('Billable time without a rate was accepted.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('hourly_rate_required', $exception->errorCode);
        }

        $this->assertSame(0, DB::table('finance_project_time_entries')->count());
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_ledger_uses_positive_minor_units_cas_append_only_corrections_filters_and_activities(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 14:00:00');

        $created = app(CreateLedgerEntry::class)->handle(new CreateLedgerEntryData(
            $project['id'], 'out', 12345, 'eur', (int) $owner->id, $at,
            occurredOn: new DateTimeImmutable('2026-08-20'), title: 'Hosting',
        ));
        $this->assertSame(12345, $created->amountMinor);
        $this->assertSame('EUR', $created->currency);

        $corrected = app(UpdateLedgerEntry::class)->handle(
            $project['id'], $created->uuid, 0,
            new CreateLedgerEntryData($project['id'], 'in', 5000, 'EUR', (int) $owner->id, $at,
                occurredOn: new DateTimeImmutable('2026-08-21'), title: 'Refund'),
        );
        $this->assertNotSame($created->uuid, $corrected->uuid);
        $this->assertNotNull(DB::table('finance_project_ledger_entries')->where('uuid', $created->uuid)->value('deleted_at'));
        $this->assertSame($created->uuid, DB::table('finance_project_ledger_entries')->where('uuid', $corrected->uuid)->value('legacy_metadata->corrects_uuid'));

        $page = app(ListProjectLedger::class)->handle($project['id'], direction: 'in', perPage: 500);
        $this->assertSame(100, $page['per_page']);
        $this->assertSame([$corrected->uuid], array_column($page['items'], 'uuid'));

        app(DeleteLedgerEntry::class)->handle($project['id'], $corrected->uuid, 0, (int) $owner->id, $at);
        $this->assertSame(0, app(ListProjectLedger::class)->handle($project['id'])['total']);
        $this->assertSame(
            ['ledger_entry.created', 'ledger_entry.corrected', 'ledger_entry.deleted'],
            DB::table('finance_project_activities')->where('type', 'like', 'ledger_entry.%')->orderBy('id')->pluck('type')->all(),
        );

        foreach ([0, -1] as $invalidAmount) {
            $this->expectException(InvalidArgumentException::class);
            new CreateLedgerEntryData($project['id'], 'out', $invalidAmount, 'EUR', (int) $owner->id, $at);
        }
    }

    public function test_invoice_time_groups_exact_frozen_lines_and_replay_does_not_create_a_second_draft(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 15:00:00');
        $this->app->instance(ProjectRateSource::class, new ProjectRateStub(Money::fromMinor(10001, 'EUR')));
        $first = app(LogProjectTime::class)->handle(new LogTimeData($project['id'], null, new DateTimeImmutable('2026-08-28'), '1.0000', (int) $owner->id, $at));
        $second = app(LogProjectTime::class)->handle(new LogTimeData($project['id'], null, new DateTimeImmutable('2026-08-28'), '0.5000', (int) $owner->id, $at));
        $fake = new ProjectInvoiceStub;
        $this->app->instance(ProjectToInvoicePort::class, $fake);
        $data = new InvoiceTimeData($project['id'], [$first->uuid, $second->uuid], 'invoice-key', (int) $owner->id, $at);
        $target = app(CreateInvoiceDraftFromTime::class)->handle($data);
        $replayed = app(CreateInvoiceDraftFromTime::class)->handle($data);
        $this->assertSame('legacy-invoice:77', $target->targetReference);
        $this->assertSame($target->targetReference, $replayed->targetReference);
        $this->assertSame(1, $fake->calls);
        $this->assertSame(15000, $fake->lines[0]->hoursScaled);
        $this->assertSame(15002, $fake->lines[0]->valueMinor);
        $this->assertSame(['legacy-invoice:77'], DB::table('finance_project_time_entries')->distinct()->pluck('invoice_target_reference')->all());
        $this->assertSame(1, DB::table('finance_project_activities')->where('type', 'time_entries.invoiced')->count());
    }

    public function test_invoice_claim_blocks_a_different_key_before_the_external_port_and_recovers_same_key_after_error(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 16:00:00');
        $this->app->instance(ProjectRateSource::class, new ProjectRateStub(Money::fromMinor(10000, 'EUR')));
        $entry = app(LogProjectTime::class)->handle(new LogTimeData($project['id'], null, new DateTimeImmutable('2026-08-28'), '1', (int) $owner->id, $at));
        $fake = new ProjectInvoiceStub;
        $fake->failOnce = true;
        $blockedCode = null;
        $fake->duringCall = function () use ($project, $entry, $owner, $at, &$blockedCode): void {
            try {
                app(CreateInvoiceDraftFromTime::class)->handle(new InvoiceTimeData($project['id'], [$entry->uuid], 'overlapping-key', (int) $owner->id, $at));
            } catch (InvalidProjectAction $exception) {
                $blockedCode = $exception->errorCode;
            }
        };
        $this->app->instance(ProjectToInvoicePort::class, $fake);
        $data = new InvoiceTimeData($project['id'], [$entry->uuid], 'recover-key', (int) $owner->id, $at);
        try {
            app(CreateInvoiceDraftFromTime::class)->handle($data);
            $this->fail('Port failure was swallowed.');
        } catch (DomainException $e) {
            $this->assertSame('port_failed', $e->getMessage());
        }
        $this->assertSame('time_entry_invoiced', $blockedCode);
        $this->assertSame('failed', DB::table('finance_project_operations')->value('state'));
        try {
            app(CreateInvoiceDraftFromTime::class)->handle(new InvoiceTimeData($project['id'], [$entry->uuid], 'different-key', (int) $owner->id, $at));
            $this->fail('A second key crossed the persisted claim.');
        } catch (InvalidProjectAction $e) {
            $this->assertSame('time_entry_invoiced', $e->errorCode);
        }
        $target = app(CreateInvoiceDraftFromTime::class)->handle($data);
        $this->assertSame('legacy-invoice:77', $target->targetReference);
        $this->assertSame(2, $fake->calls);
    }

    public function test_ledger_references_are_owner_validated_on_create_and_correction(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 17:00:00');
        $refs = new ProjectReferenceSpy;
        $this->app->instance(ProjectReferenceResolver::class, $refs);
        $created = app(CreateLedgerEntry::class)->handle(new CreateLedgerEntryData($project['id'], 'out', 100, 'EUR', (int) $owner->id, $at, categoryReference: 'legacy-category:1', paymentMethodReference: 'legacy-payment-method:2'));
        app(UpdateLedgerEntry::class)->handle($project['id'], $created->uuid, 0, new CreateLedgerEntryData($project['id'], 'out', 200, 'EUR', (int) $owner->id, $at, categoryReference: 'legacy-category:3', paymentMethodReference: 'legacy-payment-method:4'));
        $this->assertSame(['legacy-category:1', 'legacy-category:3'], $refs->categories);
        $this->assertSame(['legacy-payment-method:2', 'legacy-payment-method:4'], $refs->payments);
    }

    public function test_foreign_ledger_references_are_rejected_without_a_write(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $project = $this->storedProject($owner);
        $at = new DateTimeImmutable('2026-08-28 17:30:00');
        $category = new FinanceCategory;
        $category->forceFill(['user_id' => $foreign->id, 'name' => 'Foreign'])->save();
        $method = new PaymentMethod;
        $method->forceFill(['user_id' => $foreign->id, 'type' => 'bank', 'name' => 'Foreign', 'scope' => 'business', 'business' => true, 'version' => 0])->save();
        foreach ([['legacy-category:'.$category->id, null], [null, 'legacy-payment-method:'.$method->id]] as [$categoryRef,$methodRef]) {
            try {
                app(CreateLedgerEntry::class)->handle(new CreateLedgerEntryData($project['id'], 'out', 100, 'EUR', (int) $owner->id, $at, categoryReference: $categoryRef, paymentMethodReference: $methodRef));
                $this->fail('Foreign ledger reference accepted.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('finance_project_ledger_entries')->count());
    }

    public function test_totals_deduplicate_a_receipt_when_its_settlement_transaction_is_present(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $this->app->instance(ProjectFinancialSource::class, new ProjectFinancialStub([
            new ProjectFinancialSourceRow('bank-transaction:8', 1000, 'EUR', new DateTimeImmutable('2026-08-28')),
            new ProjectFinancialSourceRow('finance-receipt:9', -1000, 'EUR', new DateTimeImmutable('2026-08-28'), ['bank-transaction:8']),
        ]));
        $this->assertSame(1000, app(GetProjectTotals::class)->handle($project['id'])->currencies['EUR']['financial_minor']);
        $native = $this->storedProject($owner);
        $this->assertSame([], app(LegacyProjectFinancialSource::class)->rows((int) $owner->id, $native['id']));
    }

    public function test_totals_fail_stably_instead_of_overflowing_or_using_floats(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $this->app->instance(ProjectFinancialSource::class, new ProjectFinancialStub([
            new ProjectFinancialSourceRow('external:1', PHP_INT_MAX, 'EUR', new DateTimeImmutable('2026-08-28')),
            new ProjectFinancialSourceRow('external:2', 1, 'EUR', new DateTimeImmutable('2026-08-28')),
        ]));
        try {
            app(GetProjectTotals::class)->handle($project['id']);
            $this->fail('Overflow was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('project_total_overflow', $exception->getMessage());
        }
    }

    public function test_legacy_invoice_adapter_rejects_multiple_currencies_without_creating_a_draft(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $view = app(ProjectRepository::class)->get($project['id']);
        try {
            app(LegacyInvoiceDraftFromTimeAdapter::class)->createDraft((int) $owner->id, $view, [new InvoiceTimeLine(10000, 10000, 10000, 'EUR', 'A'), new InvoiceTimeLine(10000, 10000, 10000, 'USD', 'B')], ['a', 'b'], 'key');
            $this->fail('Mixed currencies were mislabeled.');
        } catch (InvalidProjectAction $e) {
            $this->assertSame('invoice_time_currency_mismatch', $e->errorCode);
        }
        $this->assertSame(0, Invoice::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{record_id: int, id: ProjectId}
     */
    private function storedProject(User $owner, array $overrides = []): array
    {
        $uuid = (string) Str::uuid();
        $id = (int) DB::table('finance_project_records')->insertGetId([
            'user_id' => $owner->id, 'uuid' => $uuid, 'parent_project_id' => null,
            'source_type' => null, 'source_id' => null, 'name' => 'Work project', 'kind' => 'business',
            'status' => 'active', 'partner_reference' => null, 'starts_on' => null, 'due_on' => null,
            'budget_minor' => null, 'currency' => 'EUR', 'version' => 0, 'archived_at' => null,
            'created_by' => $owner->id, 'created_at' => '2026-08-28 09:00:00', 'updated_at' => '2026-08-28 09:00:00',
            ...$overrides,
        ]);

        return ['record_id' => $id, 'id' => new ProjectId((int) $owner->id, $uuid)];
    }
}

final class ProjectRateStub implements ProjectRateSource
{
    public function __construct(public ?Money $rate) {}

    public function frozenRate(int $ownerId, ?string $partnerReference, string $currency): ?Money
    {
        return $this->rate;
    }
}

final class ProjectInvoiceStub implements ProjectToInvoicePort
{
    public int $calls = 0;

    public array $lines = [];

    public bool $failOnce = false;

    public ?\Closure $duringCall = null;

    public function createDraft(int $ownerId, ProjectView $project, array $lines, array $timeEntryUuids, string $idempotencyKey): InvoiceDraftTarget
    {
        $this->calls++;
        $this->lines = $lines;
        if ($this->duringCall !== null) {
            $callback = $this->duringCall;
            $this->duringCall = null;
            $callback();
        }
        if ($this->failOnce) {
            $this->failOnce = false;
            throw new DomainException('port_failed');
        }

        return new InvoiceDraftTarget('legacy-invoice:77', new ProjectDocumentSourceRef('legacy_invoice', 'legacy-invoice:77'), 'invoice.show');
    }
}

final class ProjectReferenceSpy implements ProjectReferenceResolver
{
    public array $categories = [];

    public array $payments = [];

    public function assertOwnedPartnerReference(int $ownerId, ?string $reference): void {}

    public function assertOwnedProductReference(int $ownerId, ?string $reference): void {}

    public function assertOwnedCategoryReference(int $ownerId, ?string $reference): void
    {
        $this->categories[] = $reference;
    }

    public function assertOwnedPaymentMethodReference(int $ownerId, ?string $reference): void
    {
        $this->payments[] = $reference;
    }
}

final readonly class ProjectFinancialStub implements ProjectFinancialSource
{
    public function __construct(private array $sourceRows) {}

    public function rows(int $ownerId, ProjectId $projectId): array
    {
        return $this->sourceRows;
    }
}
