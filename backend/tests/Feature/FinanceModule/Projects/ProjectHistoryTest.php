<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\AppendDocumentNote;
use App\Modules\Finance\Application\Commands\Projects\AppendProjectNote;
use App\Modules\Finance\Application\DTOs\Projects\AppendDocumentNoteData;
use App\Modules\Finance\Application\DTOs\Projects\AppendProjectNoteData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectNoteFilter;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectHistoryRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Queries\Projects\ListDocumentNotes;
use App\Modules\Finance\Application\Queries\Projects\ListProjectActivity;
use App\Modules\Finance\Application\Queries\Projects\ListProjectNotes;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectDocumentRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectHistoryRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentQuoteRepository;
use App\Modules\Finance\Infrastructure\Persistence\Exception\AppendOnlyRecordMutation;
use App\Modules\Finance\Infrastructure\Persistence\Exception\PublishedRevisionMutation;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentNoteRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectNoteRecord;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class ProjectHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_binds_project_history_without_replacing_existing_finance_ports(): void
    {
        $this->assertInstanceOf(EloquentProjectHistoryRepository::class, $this->app->make(ProjectHistoryRepository::class));
        $this->assertInstanceOf(EloquentProjectRepository::class, $this->app->make(ProjectRepository::class));
        $this->assertInstanceOf(EloquentProjectDocumentRepository::class, $this->app->make(ProjectDocumentRepository::class));
        $this->assertInstanceOf(EloquentInvoiceRepository::class, $this->app->make(InvoiceRepository::class));
        $this->assertInstanceOf(EloquentQuoteRepository::class, $this->app->make(QuoteRepository::class));
        $this->assertInstanceOf(AppendProjectNote::class, $this->app->make(AppendProjectNote::class));
        $this->assertInstanceOf(ListProjectActivity::class, $this->app->make(ListProjectActivity::class));
    }

    public function test_history_snapshot_migration_round_trips_portably(): void
    {
        $migration = require database_path('migrations/2027_03_04_140000_create_project_history_snapshots.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('finance_project_history_snapshot_items'));
        $this->assertFalse(Schema::hasTable('finance_project_history_snapshots'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('finance_project_history_snapshots'));
        $this->assertTrue(Schema::hasTable('finance_project_history_snapshot_items'));
    }

    public function test_note_dtos_reject_invalid_values_and_canonicalize_series_uuid(): void
    {
        $project = new ProjectId(1, (string) Str::uuid());
        $at = new DateTimeImmutable('2026-08-29 10:00:00.123456');

        foreach ([
            static fn () => new AppendProjectNoteData($project, 'memo', 'internal', 'Body', 1, $at),
            static fn () => new AppendProjectNoteData($project, 'note', 'public', 'Body', 1, $at),
            static fn () => new AppendProjectNoteData($project, 'note', 'internal', ' ', 1, $at),
            static fn () => new AppendProjectNoteData($project, 'correction', 'internal', 'Body', 1, $at),
            static fn () => new AppendProjectNoteData($project, 'note', 'internal', 'Body', 1, $at, 7),
            static fn () => new AppendProjectNoteData($project, 'note', 'internal', 'Body', 0, $at),
            static fn () => new ProjectNoteFilter(types: ['memo']),
            static fn () => new ProjectNoteFilter(perPage: 101),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Invalid history input was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $uuid = strtoupper((string) Str::uuid());
        $data = new AppendDocumentNoteData(1, $uuid, null, 'note', 'customer', ' Body ', 1, $at);
        $this->assertSame(strtolower($uuid), $data->seriesUuid);
        $this->assertSame('Body', $data->body);
    }

    public function test_project_note_append_and_correction_are_atomic_owner_scoped_history(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        $repository = new EloquentProjectHistoryRepository;
        $command = new AppendProjectNote($repository);

        $original = $command->handle(new AppendProjectNoteData(
            $project, 'decision', 'customer', 'Ship on Friday', (int) $owner->id,
            new DateTimeImmutable('2026-08-29 10:00:00.123456'),
        ));
        $correction = $command->handle(new AppendProjectNoteData(
            $project, 'correction', 'internal', 'Ship on Monday', (int) $owner->id,
            new DateTimeImmutable('2026-08-29 10:01:00.654321'), $original->sourceId,
        ));

        $this->assertSame('Ship on Friday', DB::table('finance_project_notes')->where('id', $original->sourceId)->value('body'));
        $this->assertSame($original->sourceId, $correction->supersedesNoteId);
        $this->assertSame(2, DB::table('finance_project_notes')->where('project_id', $projectRowId)->count());
        $this->assertSame(2, DB::table('finance_project_activities')->where('type', 'project.note_added')->count());
        $this->assertSame('2026-08-29 10:01:00.654321', DB::table('finance_project_notes')->where('id', $correction->sourceId)->value('created_at'));

        try {
            $command->handle(new AppendProjectNoteData(
                $project, 'note', 'internal', 'Delegated', (int) $foreign->id,
                new DateTimeImmutable('2026-08-29 11:00:00'),
            ));
            $this->fail('A foreign actor appended an owner note.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(2, DB::table('finance_project_notes')->where('project_id', $projectRowId)->count());

        [, $otherProjectRowId] = $this->project($owner);
        $otherNoteId = $this->projectNote($owner, $otherProjectRowId, 'Other project', 'note', 'internal', '2026-08-29 10:30:00');
        try {
            $command->handle(new AppendProjectNoteData(
                $project, 'correction', 'internal', 'Cross-project', (int) $owner->id,
                new DateTimeImmutable('2026-08-29 10:31:00'), $otherNoteId,
            ));
            $this->fail('A note from another owned project was superseded.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ModelNotFoundException::class);
        $command->handle(new AppendProjectNoteData(
            new ProjectId((int) $foreign->id, $project->uuid), 'note', 'internal', 'Foreign', (int) $foreign->id,
            new DateTimeImmutable('2026-08-29 12:00:00'),
        ));
    }

    public function test_document_note_append_validates_owner_revision_and_correction_without_project_activity(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$seriesUuid, $seriesId, $revisionId] = $this->series($owner);
        $this->linkSeries($owner, $projectRowId, $seriesUuid, $seriesId, $revisionId, true);
        $repository = new EloquentProjectHistoryRepository;
        $command = new AppendDocumentNote($repository);

        $original = $command->handle(new AppendDocumentNoteData(
            (int) $owner->id, strtoupper($seriesUuid), $revisionId, 'meeting', 'customer', 'Minutes',
            (int) $owner->id, new DateTimeImmutable('2026-08-29 12:00:00.111222'),
        ));
        $correction = $command->handle(new AppendDocumentNoteData(
            (int) $owner->id, $seriesUuid, $revisionId, 'correction', 'internal', 'Corrected minutes',
            (int) $owner->id, new DateTimeImmutable('2026-08-29 12:01:00.333444'), $original->sourceId,
        ));

        $this->assertSame($original->sourceId, $correction->supersedesNoteId);
        $this->assertSame('Minutes', DB::table('finance_document_notes')->where('id', $original->sourceId)->value('body'));
        $this->assertSame(0, DB::table('finance_project_activities')->where('project_id', $projectRowId)->count());

        [$otherSeriesUuid, $otherSeriesId, $otherRevisionId] = $this->series($owner);
        $otherNoteId = $this->documentNote($owner, $otherSeriesId, 'Other series', 'note', 'internal', '2026-08-29 12:01:30');
        foreach ([
            new AppendDocumentNoteData(
                (int) $owner->id, $seriesUuid, $otherRevisionId, 'note', 'internal', 'Wrong revision',
                (int) $owner->id, new DateTimeImmutable('2026-08-29 12:01:31'),
            ),
            new AppendDocumentNoteData(
                (int) $owner->id, $seriesUuid, null, 'correction', 'internal', 'Wrong parent',
                (int) $owner->id, new DateTimeImmutable('2026-08-29 12:01:32'), $otherNoteId,
            ),
        ] as $invalid) {
            try {
                $command->handle($invalid);
                $this->fail("A document note crossed its series boundary ({$otherSeriesUuid}).");
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(ModelNotFoundException::class);
        $command->handle(new AppendDocumentNoteData(
            (int) $foreign->id, $seriesUuid, $revisionId, 'note', 'internal', 'Foreign',
            (int) $foreign->id, new DateTimeImmutable('2026-08-29 12:02:00'),
        ));
    }

    public function test_corrections_require_the_same_author_and_exact_nullable_revision(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$seriesUuid, $seriesId, $revisionId] = $this->series($owner);
        $at = '2026-08-29 12:00:00.000001';
        $projectNoteId = (int) DB::table('finance_project_notes')->insertGetId([
            'user_id' => $owner->id, 'project_id' => $projectRowId, 'type' => 'note',
            'visibility' => 'internal', 'body' => 'System project note', 'supersedes_note_id' => null,
            'created_by' => null, 'created_at' => $at,
        ]);
        $documentNoteId = (int) DB::table('finance_document_notes')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'document_revision_id' => null,
            'type' => 'note', 'visibility' => 'internal', 'body' => 'System series note',
            'supersedes_note_id' => null, 'created_by' => null, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $repository = new EloquentProjectHistoryRepository;

        foreach ([
            static fn () => (new AppendProjectNote($repository))->handle(new AppendProjectNoteData(
                $project, 'correction', 'internal', 'Owner rewrite', (int) $owner->id,
                new DateTimeImmutable('2026-08-29 12:01:00'), $projectNoteId,
            )),
            static fn () => (new AppendDocumentNote($repository))->handle(new AppendDocumentNoteData(
                (int) $owner->id, $seriesUuid, $revisionId, 'correction', 'internal', 'Revision rewrite',
                (int) $owner->id, new DateTimeImmutable('2026-08-29 12:02:00'), $documentNoteId,
            )),
            static fn () => (new AppendDocumentNote($repository))->handle(new AppendDocumentNoteData(
                (int) $owner->id, $seriesUuid, null, 'correction', 'internal', 'Author rewrite',
                (int) $owner->id, new DateTimeImmutable('2026-08-29 12:03:00'), $documentNoteId,
            )),
        ] as $correction) {
            try {
                $correction();
                $this->fail('A correction changed author or revision identity.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, DB::table('finance_project_notes')->where('project_id', $projectRowId)->count());
        $this->assertSame(1, DB::table('finance_document_notes')->where('document_series_id', $seriesId)->count());
    }

    public function test_note_queries_filter_owner_text_type_visibility_author_date_and_page(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$foreignProject, $foreignProjectRowId] = $this->project($foreign);
        [$seriesUuid, $seriesId] = $this->series($owner);
        $repo = new EloquentProjectHistoryRepository;

        $this->projectNote($owner, $projectRowId, 'Alpha decision', 'decision', 'customer', '2026-08-29 09:00:00.100000');
        $this->projectNote($owner, $projectRowId, 'Beta note', 'note', 'internal', '2026-08-29 10:00:00.200000');
        $this->projectNote($foreign, $foreignProjectRowId, 'Alpha foreign', 'decision', 'customer', '2026-08-29 11:00:00.300000');
        $this->documentNote($owner, $seriesId, 'Alpha document', 'decision', 'customer', '2026-08-29 09:30:00.100000');

        $filter = new ProjectNoteFilter(
            q: 'alpha', types: ['decision'], visibilities: ['customer'], authorId: (int) $owner->id,
            from: new DateTimeImmutable('2026-08-29 08:59:00'), to: new DateTimeImmutable('2026-08-29 09:59:00'),
            page: 1, perPage: 1,
        );
        $projectPage = (new ListProjectNotes($repo))->handle($project, $filter);
        $documentPage = (new ListDocumentNotes($repo))->handle((int) $owner->id, $seriesUuid, $filter);

        $this->assertSame(['Alpha decision'], array_map(static fn ($item): ?string => $item->body, $projectPage->items));
        $this->assertSame(1, $projectPage->total);
        $this->assertSame(['Alpha document'], array_map(static fn ($item): ?string => $item->body, $documentPage->items));
        $this->assertSame(1, $documentPage->total);

        $this->expectException(ModelNotFoundException::class);
        (new ListProjectNotes($repo))->handle(new ProjectId((int) $foreign->id, $project->uuid), new ProjectNoteFilter);
    }

    public function test_activity_merges_active_and_historical_series_dedupes_scrubs_and_orders_exactly(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$seriesUuid, $seriesId, $revisionId] = $this->series($owner);
        $this->linkSeries($owner, $projectRowId, $seriesUuid, $seriesId, $revisionId, false);
        $this->linkSeries($owner, $projectRowId, $seriesUuid, $seriesId, $revisionId, true);
        $time = '2026-08-29 14:00:00.123456';
        $projectActivityId = $this->projectActivity($owner, $projectRowId, 'project.changed', $time, [
            'status' => 'active', 'password' => 'secret', 'error' => 'raw database exception',
            'nested' => ['token' => 'secret', 'currency' => 'EUR'],
        ]);
        $olderDocumentId = $this->documentActivity($owner, $seriesId, $revisionId, 'revision.created', $time, [
            'revision_number' => 1,
        ]);
        $documentActivityId = $this->documentActivity($owner, $seriesId, $revisionId, 'revision.published', $time, [
            'revision_number' => 1, 'storage_path' => '/private/a.pdf', 'smtp_password' => 'secret',
            'exception_message' => 'raw failure', 'pdf_sha256' => str_repeat('a', 64),
        ]);

        $page = (new ListProjectActivity(new EloquentProjectHistoryRepository))->handle($project, null, 10);

        $this->assertSame(
            [['document', $documentActivityId], ['document', $olderDocumentId], ['project', $projectActivityId]],
            array_map(static fn ($item): array => [$item->sourceKind, $item->sourceId], $page->items),
        );
        $encoded = json_encode(array_map(static fn ($item): array => $item->payload, $page->items), JSON_THROW_ON_ERROR);
        foreach (['secret', 'password', 'error', 'storage_path', 'smtp', 'exception_message'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, mb_strtolower($encoded));
        }
        $this->assertStringContainsString('pdf_sha256', $encoded);
        $this->assertNull($page->nextCursor);
    }

    public function test_activity_payload_contract_keeps_legitimate_event_fields_and_scrubs_secrets(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$seriesUuid, $seriesId, $revisionId] = $this->series($owner);
        $this->linkSeries($owner, $projectRowId, $seriesUuid, $seriesId, $revisionId, true);
        $projectPayloads = [
            'project.created' => ['parent_uuid' => null, 'status' => 'planned'],
            'project.updated' => ['old_version' => 2, 'new_version' => 3],
            'project.status_changed' => ['old_status' => 'active', 'new_status' => 'done', 'reopened' => false],
            'project.moved' => ['old_parent_uuid' => 'old', 'new_parent_uuid' => 'new'],
            'project.archived' => ['archived' => true],
            'work_item.created' => ['work_item_uuid' => 'work-1', 'source_line_index' => 2],
            'work_item.updated' => ['work_item_uuid' => 'work-1', 'old_status' => 'open', 'new_status' => 'done'],
            'work_item.reordered' => ['ordered_uuids' => ['b', 'a']],
            'time_entry.logged' => ['time_entry_uuid' => 'time-1', 'quantity_scaled' => 25_000],
            'time_entries.invoiced' => ['target_reference' => 'INV-1', 'time_entry_uuids' => ['time-1']],
            'ledger_entry.corrected' => ['ledger_entry_uuid' => 'ledger-2', 'corrects_uuid' => 'ledger-1'],
            'project.document_attached' => [
                'link_id' => 4, 'operation_id' => 5, 'source_type' => 'finance_series',
                'source_reference' => $seriesUuid, 'role' => 'quote',
            ],
        ];
        $documentPayloads = [
            'revision.created' => ['snapshot_sha256' => str_repeat('a', 64), 'creation_key_sha256' => str_repeat('b', 64)],
            'revision.published' => ['pdf_sha256' => str_repeat('c', 64)],
            'invoice.reminder.queued' => ['delivery_id' => 4, 'level' => 2, 'retry_of_delivery_id' => 3],
            'quote.version.started' => ['version' => 3, 'based_on_revision_id' => 8],
            'quote.revision.superseded' => ['version' => 2, 'previous_revision_id' => 8, 'current_revision_id' => 9],
            'quote.duplicated' => ['version' => 1, 'source_revision_id' => 9, 'target_quote_uuid' => 'quote-2'],
            'invoice.mail.failed' => [
                'delivery_id' => 7, 'recipient_domain' => 'example.test', 'level' => 'error', 'error_code' => 'smtp_timeout',
            ],
            'payment.allocated' => ['payment_id' => 11, 'batch_id' => 12, 'allocation_id' => 13, 'amount_minor' => 1_000],
            'payment.allocation_reversed' => [
                'payment_id' => 11, 'batch_id' => 14, 'allocation_id' => 15,
                'reverses_allocation_id' => 13, 'amount_minor' => -1_000,
            ],
        ];
        $second = 0;
        foreach ($projectPayloads as $type => $payload) {
            $this->projectActivity($owner, $projectRowId, $type, sprintf('2026-08-29 14:00:%02d.000001', $second++), [
                ...$payload, 'password' => 'secret', 'exception' => 'raw error', 'path' => '/private/project',
            ]);
        }
        foreach ($documentPayloads as $type => $payload) {
            $this->documentActivity($owner, $seriesId, $revisionId, $type, sprintf('2026-08-29 14:00:%02d.000001', $second++), [
                ...$payload, 'password' => 'secret', 'exception' => 'raw error', 'path' => '/private/document',
            ]);
        }

        $page = (new ListProjectActivity(new EloquentProjectHistoryRepository))->handle($project, null, 100);
        $actual = [];
        foreach ($page->items as $item) {
            $actual[$item->type] = $item->payload;
        }
        foreach ([...$projectPayloads, ...$documentPayloads] as $type => $payload) {
            $this->assertSame($payload, $actual[$type]);
        }
        $encoded = json_encode($actual, JSON_THROW_ON_ERROR);
        foreach (['secret', 'raw error', '/private/'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_activity_cursor_freezes_high_water_and_link_snapshot_with_microsecond_keyset(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$firstSeriesUuid, $firstSeriesId, $firstRevisionId] = $this->series($owner);
        $this->linkSeries($owner, $projectRowId, $firstSeriesUuid, $firstSeriesId, $firstRevisionId, true);
        $expected = [];
        foreach ([
            ['2026-08-29 15:00:00.900000', 'project'],
            ['2026-08-29 15:00:00.800000', 'document'],
            ['2026-08-29 15:00:00.700000', 'project'],
        ] as [$time, $kind]) {
            $expected[] = $kind === 'project'
                ? ['project', $this->projectActivity($owner, $projectRowId, 'project.changed', $time, ['status' => 'active'])]
                : ['document', $this->documentActivity($owner, $firstSeriesId, $firstRevisionId, 'revision.created', $time, ['revision_number' => 1])];
        }
        $query = new ListProjectActivity(new EloquentProjectHistoryRepository);
        $first = $query->handle($project, null, 1);
        $this->assertNotNull($first->nextCursor);
        try {
            $query->handle($project, substr((string) $first->nextCursor, 0, -1).'x', 1);
            $this->fail('A tampered activity cursor was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        [$otherProject] = $this->project($owner);
        try {
            $query->handle($otherProject, $first->nextCursor, 1);
            $this->fail('An activity cursor was reused for another project.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->projectActivity($owner, $projectRowId, 'project.concurrent', '2026-08-29 15:00:00.850000', ['status' => 'active']);
        [$laterUuid, $laterSeriesId, $laterRevisionId] = $this->series($owner);
        $this->documentActivity($owner, $laterSeriesId, $laterRevisionId, 'revision.old', '2026-08-29 14:00:00.000001', ['revision_number' => 1]);
        $this->linkSeries($owner, $projectRowId, $laterUuid, $laterSeriesId, $laterRevisionId, true);

        $actual = array_map(static fn ($item): array => [$item->sourceKind, $item->sourceId], $first->items);
        $cursor = $first->nextCursor;
        while ($cursor !== null) {
            $page = $query->handle($project, $cursor, 1);
            array_push($actual, ...array_map(static fn ($item): array => [$item->sourceKind, $item->sourceId], $page->items));
            $cursor = $page->nextCursor;
        }
        $this->assertSame($expected, $actual);
        $this->assertCount(count($actual), array_unique(array_map(static fn (array $item): string => implode(':', $item), $actual)));
    }

    public function test_activity_cursor_preserves_kind_and_descending_id_ties_across_pages(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        [$seriesUuid, $seriesId, $revisionId] = $this->series($owner);
        $this->linkSeries($owner, $projectRowId, $seriesUuid, $seriesId, $revisionId, true);
        $time = '2026-08-29 16:00:00.123456';
        $projectOne = $this->projectActivity($owner, $projectRowId, 'project.one', $time, []);
        $projectTwo = $this->projectActivity($owner, $projectRowId, 'project.two', $time, []);
        $documentOne = $this->documentActivity($owner, $seriesId, $revisionId, 'document.one', $time, []);
        $documentTwo = $this->documentActivity($owner, $seriesId, $revisionId, 'document.two', $time, []);

        $query = new ListProjectActivity(new EloquentProjectHistoryRepository);
        $actual = [];
        $cursor = null;
        do {
            $page = $query->handle($project, $cursor, 1);
            array_push($actual, ...array_map(static fn ($item): array => [$item->sourceKind, $item->sourceId], $page->items));
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $this->assertSame([
            ['document', $documentTwo], ['document', $documentOne],
            ['project', $projectTwo], ['project', $projectOne],
        ], $actual);
    }

    public function test_activity_snapshot_does_not_admit_a_late_committed_lower_id(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        foreach ([[100, '2026-08-29 15:00:00.900000'], [200, '2026-08-29 15:00:00.700000']] as [$id, $at]) {
            DB::table('finance_project_activities')->insert([
                'id' => $id, 'user_id' => $owner->id, 'project_id' => $projectRowId, 'type' => 'project.changed',
                'subject_type' => null, 'subject_reference' => null, 'payload' => '{}', 'created_by' => $owner->id,
                'occurred_at' => $at, 'created_at' => $at,
            ]);
        }
        $query = new ListProjectActivity(new EloquentProjectHistoryRepository);
        $first = $query->handle($project, null, 1);
        $this->assertSame([100], array_map(static fn ($item): int => $item->sourceId, $first->items));
        $this->assertNotNull($first->nextCursor);

        DB::table('finance_project_activities')->insert([
            'id' => 150, 'user_id' => $owner->id, 'project_id' => $projectRowId, 'type' => 'project.late',
            'subject_type' => null, 'subject_reference' => null, 'payload' => '{}', 'created_by' => $owner->id,
            'occurred_at' => '2026-08-29 15:00:00.800000', 'created_at' => '2026-08-29 15:00:00.800000',
        ]);

        $second = $query->handle($project, $first->nextCursor, 10);
        $this->assertSame([200], array_map(static fn ($item): int => $item->sourceId, $second->items));
        $this->assertNull($second->nextCursor);
    }

    public function test_expired_activity_snapshot_cursor_fails_instead_of_mixing_a_new_timeline(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        $this->projectActivity($owner, $projectRowId, 'project.one', '2026-08-29 15:00:00.900000', []);
        $this->projectActivity($owner, $projectRowId, 'project.two', '2026-08-29 15:00:00.700000', []);
        $query = new ListProjectActivity(new EloquentProjectHistoryRepository);
        $first = $query->handle($project, null, 1);
        $this->assertNotNull($first->nextCursor);
        DB::table('finance_project_history_snapshots')->update(['expires_at' => '2000-01-01 00:00:00.000000']);

        try {
            $query->handle($project, $first->nextCursor, 1);
            $this->fail('An expired history snapshot silently started a new timeline.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('project_activity_cursor_expired', $exception->getMessage());
        }
    }

    public function test_first_activity_page_without_continuation_leaves_no_snapshot(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        $this->projectActivity($owner, $projectRowId, 'project.one', '2026-08-29 15:00:00.000001', []);

        $page = (new ListProjectActivity(new EloquentProjectHistoryRepository))->handle($project, null, 10);

        $this->assertCount(1, $page->items);
        $this->assertNull($page->nextCursor);
        $this->assertSame(0, DB::table('finance_project_history_snapshots')->count());
        $this->assertSame(0, DB::table('finance_project_history_snapshot_items')->count());
    }

    public function test_first_activity_page_boundedly_cleans_expired_snapshots_and_keeps_active_snapshot(): void
    {
        $owner = User::factory()->create();
        [$project, $projectRowId] = $this->project($owner);
        $expired = [];
        for ($index = 0; $index < 101; $index++) {
            $expired[] = [
                'uuid' => strtolower((string) Str::uuid()), 'user_id' => $owner->id, 'project_id' => $projectRowId,
                'expires_at' => '2000-01-01 00:00:00.000000', 'created_at' => '1999-12-31 23:00:00.000000',
            ];
        }
        DB::table('finance_project_history_snapshots')->insert($expired);
        $expiredIds = DB::table('finance_project_history_snapshots')->orderBy('id')->pluck('id')->all();
        $activeUuid = strtolower((string) Str::uuid());
        $activeId = (int) DB::table('finance_project_history_snapshots')->insertGetId([
            'uuid' => $activeUuid, 'user_id' => $owner->id, 'project_id' => $projectRowId,
            'expires_at' => '2099-01-01 00:00:00.000000', 'created_at' => '2026-08-29 15:00:00.000000',
        ]);
        $items = array_map(static fn (int|string $snapshotId): array => [
            'snapshot_id' => (int) $snapshotId, 'source_kind' => 'project',
            'source_id' => (int) $snapshotId, 'occurred_at' => '1999-12-31 23:00:00.000000',
        ], $expiredIds);
        $items[] = [
            'snapshot_id' => $activeId, 'source_kind' => 'project',
            'source_id' => $activeId, 'occurred_at' => '2026-08-29 15:00:00.000000',
        ];
        DB::table('finance_project_history_snapshot_items')->insert($items);

        $page = (new ListProjectActivity(new EloquentProjectHistoryRepository))->handle($project, null, 10);

        $this->assertSame([], $page->items);
        $this->assertNull($page->nextCursor);
        $this->assertSame(1, DB::table('finance_project_history_snapshots')->where('expires_at', '<=', now())->count());
        $this->assertTrue(DB::table('finance_project_history_snapshots')->where('uuid', $activeUuid)->exists());
        $this->assertTrue(DB::table('finance_project_history_snapshot_items')->where('snapshot_id', $activeId)->exists());
        $this->assertSame(2, DB::table('finance_project_history_snapshot_items')->count());
    }

    public function test_project_and_document_history_models_reject_every_bulk_mutation_path(): void
    {
        $owner = User::factory()->create();
        [, $projectRowId] = $this->project($owner);
        [, $seriesId, $revisionId] = $this->series($owner);
        $noteId = $this->projectNote($owner, $projectRowId, 'Immutable', 'note', 'internal', '2026-08-29 09:00:00');
        $activityId = $this->projectActivity($owner, $projectRowId, 'project.created', '2026-08-29 09:00:00', []);
        $documentNoteId = $this->documentNote($owner, $seriesId, 'Immutable document', 'note', 'internal', '2026-08-29 09:00:00');
        $documentActivityId = $this->documentActivity($owner, $seriesId, $revisionId, 'revision.created', '2026-08-29 09:00:00', []);

        foreach ([
            [ProjectNoteRecord::class, $noteId, AppendOnlyRecordMutation::class],
            [ProjectActivityRecord::class, $activityId, AppendOnlyRecordMutation::class],
            [DocumentNoteRecord::class, $documentNoteId, PublishedRevisionMutation::class],
            [DocumentActivityRecord::class, $documentActivityId, PublishedRevisionMutation::class],
        ] as [$model, $id, $exception]) {
            $record = $model::query()->withoutGlobalScopes()->findOrFail($id);
            foreach ([
                static function () use ($record): bool {
                    $record->forceFill(['type' => 'rewritten']);

                    return $record->save();
                },
                static fn (): bool => $record->deleteQuietly(),
            ] as $instanceMutation) {
                try {
                    $instanceMutation();
                    $this->fail("{$model} accepted an instance append-only mutation.");
                } catch (\Throwable $caught) {
                    $this->assertInstanceOf($exception, $caught);
                }
            }
            foreach ([
                static fn () => $model::query()->whereKey($id)->update(['type' => 'rewritten']),
                static fn () => $model::query()->updateOrInsert(['id' => $id], ['type' => 'rewritten']),
                static fn () => $model::query()->upsert([['id' => $id, 'type' => 'rewritten']], ['id'], ['type']),
                static fn () => $model::query()->whereKey($id)->delete(),
                static fn () => $model::query()->truncate(),
            ] as $mutation) {
                try {
                    $mutation();
                    $this->fail("{$model} accepted an append-only mutation.");
                } catch (\Throwable $caught) {
                    $this->assertInstanceOf($exception, $caught);
                }
            }
        }
    }

    /** @return array{ProjectId,int} */
    private function project(User $owner): array
    {
        $uuid = strtolower((string) Str::uuid());
        $id = (int) DB::table('finance_project_records')->insertGetId([
            'user_id' => $owner->id, 'uuid' => $uuid, 'parent_project_id' => null,
            'source_type' => null, 'source_id' => null, 'name' => 'History', 'kind' => 'business',
            'status' => 'active', 'partner_reference' => null, 'starts_on' => null, 'due_on' => null,
            'budget_minor' => null, 'currency' => 'EUR', 'version' => 0, 'archived_at' => null,
            'created_by' => $owner->id, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);

        return [new ProjectId((int) $owner->id, $uuid), $id];
    }

    /** @return array{string,int,int} */
    private function series(User $owner): array
    {
        $uuid = strtolower((string) Str::uuid());
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id, 'uuid' => $uuid, 'document_type' => 'quote', 'status' => 'draft',
            'source_type' => null, 'source_id' => null, 'created_by' => $owner->id,
            'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 1,
            'previous_revision_id' => null, 'status' => 'draft', 'snapshot' => '{}', 'net_minor' => 0,
            'vat_minor' => 0, 'gross_minor' => 0, 'currency' => 'EUR', 'change_reason' => null,
            'pdf_path' => null, 'pdf_sha256' => null, 'published_at' => null, 'created_by' => $owner->id,
            'created_at' => '2026-08-29 08:00:00',
        ]);

        return [$uuid, $seriesId, $revisionId];
    }

    private function linkSeries(User $owner, int $projectId, string $uuid, int $seriesId, int $revisionId, bool $active): int
    {
        return (int) DB::table('finance_project_document_links')->insertGetId([
            'user_id' => $owner->id, 'project_id' => $projectId, 'source_type' => 'finance_series',
            'source_reference' => $uuid, 'document_series_id' => $seriesId, 'pinned_revision_id' => $revisionId,
            'role' => 'quote', 'metadata_snapshot' => '{}', 'attached_by' => $owner->id,
            'attached_operation_id' => null, 'attached_at' => '2026-08-29 08:00:00',
            'detached_by' => $active ? null : $owner->id, 'detached_operation_id' => null,
            'detached_at' => $active ? null : '2026-08-29 08:01:00',
        ]);
    }

    private function projectNote(User $owner, int $projectId, string $body, string $type, string $visibility, string $at): int
    {
        return (int) DB::table('finance_project_notes')->insertGetId([
            'user_id' => $owner->id, 'project_id' => $projectId, 'type' => $type, 'visibility' => $visibility,
            'body' => $body, 'supersedes_note_id' => null, 'created_by' => $owner->id, 'created_at' => $at,
        ]);
    }

    private function documentNote(User $owner, int $seriesId, string $body, string $type, string $visibility, string $at): int
    {
        return (int) DB::table('finance_document_notes')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'document_revision_id' => null,
            'type' => $type, 'visibility' => $visibility, 'body' => $body, 'supersedes_note_id' => null,
            'created_by' => $owner->id, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function projectActivity(User $owner, int $projectId, string $type, string $at, array $payload): int
    {
        return (int) DB::table('finance_project_activities')->insertGetId([
            'user_id' => $owner->id, 'project_id' => $projectId, 'type' => $type,
            'subject_type' => null, 'subject_reference' => null,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_by' => $owner->id,
            'occurred_at' => $at, 'created_at' => $at,
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function documentActivity(User $owner, int $seriesId, int $revisionId, string $type, string $at, array $payload): int
    {
        return (int) DB::table('finance_document_activities')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId, 'type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_by' => $owner->id,
            'created_at' => $at,
        ]);
    }
}
