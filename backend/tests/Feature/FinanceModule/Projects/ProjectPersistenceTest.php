<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\User;
use App\Modules\Finance\Application\DTOs\Projects\ChangeProjectStatusData;
use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\MoveProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectListFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Queries\Projects\GetProject;
use App\Modules\Finance\Application\Queries\Projects\ListProjects;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectBudget;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectReferenceResolver;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectOperationRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectRepository;
use App\Modules\Finance\Infrastructure\Persistence\Exception\AppendOnlyRecordMutation;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectDocumentLinkRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectLedgerEntryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectNoteRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectOperationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectTimeEntryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectWorkItemRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProjectPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{class-string}> */
    public static function projectPersistenceTypes(): iterable
    {
        foreach ([
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ProjectId',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ProjectView',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ProjectPage',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ProjectListFilter',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ProjectMutationResult',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\CreateProjectData',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\UpdateProjectData',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ChangeProjectStatusData',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\MoveProjectData',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\WorkItemView',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\TimeEntryView',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\LedgerEntryView',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\ProjectDocumentSourceRef',
            'App\\Modules\\Finance\\Application\\DTOs\\Projects\\OperationReservation',
            'App\\Modules\\Finance\\Application\\Ports\\Projects\\ProjectRepository',
            'App\\Modules\\Finance\\Application\\Ports\\Projects\\ProjectOperationRepository',
            'App\\Modules\\Finance\\Application\\Ports\\Projects\\ProjectReferenceResolver',
            'App\\Modules\\Finance\\Application\\Queries\\Projects\\GetProject',
            'App\\Modules\\Finance\\Application\\Queries\\Projects\\ListProjects',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectWorkItemRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectTimeEntryRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectLedgerEntryRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectDocumentLinkRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectNoteRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectActivityRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\ProjectOperationRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\EloquentProjectRepository',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\EloquentProjectOperationRepository',
            'App\\Modules\\Finance\\Infrastructure\\Compatibility\\LegacyProjectReferenceResolver',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Exception\\AppendOnlyRecordMutation',
        ] as $class) {
            yield $class => [$class];
        }
    }

    #[DataProvider('projectPersistenceTypes')]
    public function test_task_four_persistence_surface_is_available(string $class): void
    {
        $this->assertTrue(class_exists($class) || interface_exists($class), $class);
    }

    public function test_owner_filters_hierarchy_and_note_search_never_cross_owner_boundaries(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $parent = $this->storedProject($owner, [
            'name' => 'Parent', 'status' => 'active', 'kind' => 'business',
        ]);
        $child = $this->storedProject($owner, [
            'name' => 'Filtered child', 'kind' => 'private', 'status' => 'planned',
            'partner_reference' => 'legacy-partner:17',
            'parent_project_id' => $parent['record_id'],
            'starts_on' => '2026-01-15', 'due_on' => '2026-02-20',
        ]);
        $this->storedProject($owner, ['name' => 'Unrelated owner project']);
        $foreignProject = $this->storedProject($foreign, ['name' => 'Foreign needle']);
        $this->storedNote($owner, $child['record_id'], 'The private needle is in this note.');
        $this->storedNote($foreign, $foreignProject['record_id'], 'needle');

        $filter = new ProjectListFilter(
            ownerId: $owner->id,
            q: 'NEEDLE',
            status: ProjectStatus::Planned,
            kind: ProjectKind::Private,
            partnerReference: 'legacy-partner:17',
            parentUuid: $parent['id']->uuid,
            startsFrom: new DateTimeImmutable('2026-01-01'),
            startsTo: new DateTimeImmutable('2026-01-31'),
            dueFrom: new DateTimeImmutable('2026-02-01'),
            dueTo: new DateTimeImmutable('2026-02-28'),
            sort: 'name',
            direction: 'asc',
            perPage: 10,
        );
        $page = app(ListProjects::class)->handle($filter);

        $this->assertSame(1, $page->total);
        $this->assertSame([$child['id']->uuid], array_map(
            static fn ($project): string => $project->id->uuid,
            $page->items,
        ));
        $view = app(GetProject::class)->handle($child['id']);
        $this->assertSame($parent['id']->uuid, $view->parentId?->uuid);
        $this->assertTrue($view->parentAvailable);

        DB::table('finance_project_records')->where('id', $parent['record_id'])->update([
            'archived_at' => '2026-08-28 12:00:00',
        ]);
        $this->assertFalse(app(ProjectRepository::class)->get($child['id'])->parentAvailable);
        $this->assertSame(0, app(ProjectRepository::class)->page(new ProjectListFilter(
            ownerId: $owner->id,
            parentUuid: $foreignProject['id']->uuid,
        ))->total);

        $this->actingAs($foreign);
        $this->assertSame($child['id']->uuid, app(ProjectRepository::class)->get($child['id'])->id->uuid);
        $this->expectException(ModelNotFoundException::class);
        app(ProjectRepository::class)->get(new ProjectId($owner->id, $foreignProject['id']->uuid));
    }

    public function test_pages_are_bounded_and_stable_for_every_allowed_sort(): void
    {
        $owner = User::factory()->create();
        $first = $this->storedProject($owner, ['name' => 'Same', 'updated_at' => '2026-08-28 10:00:00']);
        $second = $this->storedProject($owner, ['name' => 'Same', 'updated_at' => '2026-08-28 10:00:00']);
        $third = $this->storedProject($owner, ['name' => 'Same', 'updated_at' => '2026-08-28 10:00:00']);

        $pageOne = app(ProjectRepository::class)->page(new ProjectListFilter(
            ownerId: $owner->id, sort: 'name', direction: 'asc', page: 1, perPage: 1,
        ));
        $pageTwo = app(ProjectRepository::class)->page(new ProjectListFilter(
            ownerId: $owner->id, sort: 'name', direction: 'asc', page: 2, perPage: 1,
        ));
        $descending = app(ProjectRepository::class)->page(new ProjectListFilter(
            ownerId: $owner->id, sort: 'updated_at', direction: 'desc', page: 1, perPage: 3,
        ));

        $this->assertSame($first['id']->uuid, $pageOne->items[0]->id->uuid);
        $this->assertSame($second['id']->uuid, $pageTwo->items[0]->id->uuid);
        $this->assertSame([
            $third['id']->uuid, $second['id']->uuid, $first['id']->uuid,
        ], array_map(static fn ($project): string => $project->id->uuid, $descending->items));
        foreach (['updated_at', 'name', 'starts_on', 'due_on', 'status'] as $sort) {
            $this->assertSame($sort, new ProjectListFilter($owner->id, sort: $sort)->sort);
        }

        foreach ([
            static fn () => new ProjectListFilter($owner->id, sort: 'created_at'),
            static fn () => new ProjectListFilter($owner->id, direction: 'sideways'),
            static fn () => new ProjectListFilter($owner->id, page: 0),
            static fn () => new ProjectListFilter($owner->id, perPage: 101),
        ] as $invalidFilter) {
            try {
                $invalidFilter();
                $this->fail('Invalid pagination/filter input was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_each_allowed_sort_has_portable_deterministic_null_and_id_ordering(): void
    {
        $owner = User::factory()->create();
        $a = $this->storedProject($owner, [
            'name' => 'Bravo', 'status' => 'active', 'starts_on' => '2026-01-03',
            'due_on' => '2026-01-01', 'updated_at' => '2026-01-03 10:00:00',
        ]);
        $b = $this->storedProject($owner, [
            'name' => 'Alpha', 'status' => 'planned', 'starts_on' => '2026-01-01',
            'due_on' => null, 'updated_at' => '2026-01-01 10:00:00',
        ]);
        $c = $this->storedProject($owner, [
            'name' => 'Charlie', 'status' => 'done', 'starts_on' => null,
            'due_on' => '2026-01-02', 'updated_at' => '2026-01-02 10:00:00',
        ]);
        $expected = [
            'updated_at' => [$b['id']->uuid, $c['id']->uuid, $a['id']->uuid],
            'name' => [$b['id']->uuid, $a['id']->uuid, $c['id']->uuid],
            'starts_on' => [$b['id']->uuid, $a['id']->uuid, $c['id']->uuid],
            'due_on' => [$a['id']->uuid, $c['id']->uuid, $b['id']->uuid],
            'status' => [$a['id']->uuid, $c['id']->uuid, $b['id']->uuid],
        ];

        foreach ($expected as $sort => $uuids) {
            $page = app(ProjectRepository::class)->page(new ProjectListFilter(
                ownerId: $owner->id, sort: $sort, direction: 'asc', perPage: 100,
            ));
            $this->assertSame($uuids, array_map(
                static fn ($project): string => $project->id->uuid,
                $page->items,
            ), $sort);
        }
    }

    public function test_text_search_treats_like_wildcards_and_escape_character_as_literals(): void
    {
        $owner = User::factory()->create();
        $literalPercent = $this->storedProject($owner, ['name' => 'Percent % marker']);
        $this->storedProject($owner, ['name' => 'Percent XX marker']);
        $literalUnderscore = $this->storedProject($owner, ['name' => 'Under _ marker']);
        $this->storedProject($owner, ['name' => 'Under X marker']);
        $literalEscape = $this->storedProject($owner, ['name' => 'Escape ! marker']);
        $this->storedProject($owner, ['name' => 'Escape X marker']);

        foreach ([
            '%' => $literalPercent['id']->uuid,
            '_' => $literalUnderscore['id']->uuid,
            '!' => $literalEscape['id']->uuid,
        ] as $query => $expectedUuid) {
            $page = app(ProjectRepository::class)->page(new ProjectListFilter(
                ownerId: $owner->id,
                q: $query,
                sort: 'name',
                direction: 'asc',
                perPage: 100,
            ));
            $this->assertSame([$expectedUuid], array_map(
                static fn ($project): string => $project->id->uuid,
                $page->items,
            ), $query);
        }
    }

    public function test_create_and_compare_and_swap_writes_return_the_current_owner_aggregate(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $projects = app(ProjectRepository::class);
        $at = new DateTimeImmutable('2026-08-28 11:00:00+00:00');
        $parent = $projects->create(new CreateProjectData(
            $owner->id, 'Parent', ProjectKind::Business,
            ProjectBudget::fromMinor(500_00, 'eur'), $owner->id, $at,
        ));
        $child = $projects->create(new CreateProjectData(
            $owner->id, 'Child', ProjectKind::Private,
            ProjectBudget::fromMinor(null, 'USD'), $owner->id, $at,
            parentId: $parent->id,
            startsOn: new DateTimeImmutable('2026-09-01'),
            dueOn: new DateTimeImmutable('2026-09-30'),
        ));

        $this->assertSame('EUR', $parent->currency);
        $this->assertSame(500_00, $parent->budgetMinor);
        $this->assertSame($parent->id->uuid, $child->parentId?->uuid);

        $updatedResult = $projects->update(new UpdateProjectData(
            $child->id, 0, 'Updated child', ProjectKind::Business,
            ProjectBudget::fromMinor(123_45, 'EUR'), $owner->id, $at,
        ));
        $this->assertInstanceOf(ProjectMutationResult::class, $updatedResult);
        $this->assertTrue($updatedResult->applied);
        $updated = $updatedResult->current;
        $staleResult = $projects->update(new UpdateProjectData(
            $child->id, 0, 'Stale overwrite', ProjectKind::Private,
            ProjectBudget::fromMinor(1, 'EUR'), $owner->id, $at,
        ));
        $this->assertFalse($staleResult->applied);
        $stale = $staleResult->current;
        $this->assertSame(1, $updated->version);
        $this->assertSame('Updated child', $stale->name);
        $this->assertSame(1, $stale->version);

        $active = $projects->changeStatus(new ChangeProjectStatusData(
            $child->id, 1, ProjectStatus::Active, $owner->id, $at,
        ))->current;
        $moved = $projects->move(new MoveProjectData($child->id, 2, null, $owner->id, $at))->current;
        $archived = $projects->archive($child->id, 3)->current;
        $this->assertSame(ProjectStatus::Active, $active->status);
        $this->assertNull($moved->parentId);
        $this->assertTrue($archived->archived);
        $this->assertSame(0, $projects->page(new ProjectListFilter(
            ownerId: $owner->id, q: 'Updated child',
        ))->total);
        $this->assertSame(1, $projects->page(new ProjectListFilter(
            ownerId: $owner->id, q: 'Updated child', archived: true,
        ))->total);
        $restored = $projects->restore($child->id, 4)->current;
        $this->assertFalse($restored->archived);
        $this->assertSame(5, $restored->version);

        $foreignParent = $this->storedProject($foreign);
        $before = DB::table('finance_project_records')->where('user_id', $owner->id)->count();
        try {
            $projects->create(new CreateProjectData(
                $owner->id, 'Cross owner child', ProjectKind::Private,
                ProjectBudget::fromMinor(null, 'EUR'), $owner->id, $at,
                parentId: $foreignParent['id'],
            ));
            $this->fail('Cross-owner parent was accepted.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, DB::table('finance_project_records')->where('user_id', $owner->id)->count());
        }
    }

    public function test_create_rejects_an_archived_parent_after_locking_it(): void
    {
        $owner = User::factory()->create();
        $parent = $this->storedProject($owner, [
            'name' => 'Archived parent',
            'archived_at' => '2026-08-28 10:00:00',
        ]);
        $before = DB::table('finance_project_records')->where('user_id', $owner->id)->count();

        try {
            app(ProjectRepository::class)->create(new CreateProjectData(
                $owner->id,
                'Rejected child',
                ProjectKind::Private,
                ProjectBudget::fromMinor(null, 'EUR'),
                $owner->id,
                new DateTimeImmutable('2026-08-28 11:00:00'),
                parentId: $parent['id'],
            ));
            $this->fail('An archived parent was accepted.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('project_parent_archived', $exception->errorCode);
            $this->assertSame($before, DB::table('finance_project_records')->where('user_id', $owner->id)->count());
        }
    }

    public function test_move_atomically_rejects_archived_parents_deep_cycles_and_opposite_moves(): void
    {
        $owner = User::factory()->create();
        $parent = $this->storedProject($owner, ['name' => 'Parent']);
        $child = $this->storedProject($owner, [
            'name' => 'Child', 'parent_project_id' => $parent['record_id'],
        ]);
        $grandchild = $this->storedProject($owner, [
            'name' => 'Grandchild', 'parent_project_id' => $child['record_id'],
        ]);
        $archived = $this->storedProject($owner, [
            'name' => 'Archived', 'archived_at' => '2026-08-28 10:00:00',
        ]);
        $projects = app(ProjectRepository::class);
        $at = new DateTimeImmutable('2026-08-28 11:00:00');

        foreach ([
            [new MoveProjectData($parent['id'], 0, $grandchild['id'], $owner->id, $at), 'project_parent_cycle'],
            [new MoveProjectData($grandchild['id'], 0, $archived['id'], $owner->id, $at), 'project_parent_archived'],
        ] as [$move, $errorCode]) {
            try {
                $projects->move($move);
                $this->fail("Invalid hierarchy move {$errorCode} was accepted.");
            } catch (InvalidProjectAction $exception) {
                $this->assertSame($errorCode, $exception->errorCode);
            }
        }
        $this->assertNull(DB::table('finance_project_records')->where('id', $parent['record_id'])->value('parent_project_id'));
        $this->assertSame($child['record_id'], DB::table('finance_project_records')->where('id', $grandchild['record_id'])->value('parent_project_id'));

        $left = $this->storedProject($owner, ['name' => 'Left']);
        $right = $this->storedProject($owner, ['name' => 'Right']);
        $firstMove = $projects->move(new MoveProjectData($left['id'], 0, $right['id'], $owner->id, $at));
        $this->assertTrue($firstMove->applied);
        try {
            $projects->move(new MoveProjectData($right['id'], 0, $left['id'], $owner->id, $at));
            $this->fail('Opposite hierarchy moves created a two-node cycle.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('project_parent_cycle', $exception->errorCode);
            $this->assertNull(DB::table('finance_project_records')->where('id', $right['record_id'])->value('parent_project_id'));
        }
    }

    public function test_hierarchy_lock_query_uses_ascending_project_id_order(): void
    {
        $owner = User::factory()->create();
        $child = $this->storedProject($owner, ['name' => 'Child']);
        $parent = $this->storedProject($owner, ['name' => 'Parent']);
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ProjectRepository::class)->move(new MoveProjectData(
            $child['id'],
            0,
            $parent['id'],
            $owner->id,
            new DateTimeImmutable('2026-08-28 11:00:00'),
        ));

        $lockQuery = collect(DB::getQueryLog())->first(static fn (array $query): bool => str_contains((string) $query['query'], 'finance_project_records')
            && str_contains((string) $query['query'], 'order by'));
        $this->assertIsArray($lockQuery);
        $this->assertMatchesRegularExpression('/order by ["`]id["`] asc/i', (string) $lockQuery['query']);
        $this->assertSame($owner->id, $lockQuery['bindings'][0] ?? null);
    }

    public function test_operation_reservations_are_owner_scoped_and_replay_only_the_same_request(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $project = $this->storedProject($owner);
        $foreignProject = $this->storedProject($foreign);
        $operations = app(ProjectOperationRepository::class);
        $hash = hash('sha256', 'request-a');

        $new = $operations->reserve($owner->id, 'attach_document', 'action-1', $hash, $project['id']);
        $inProgress = $operations->reserve($owner->id, 'attach_document', 'action-1', $hash, $project['id']);
        $this->assertSame('new', $new->status);
        $this->assertSame('in_progress', $inProgress->status);

        $operations->succeed($new, ['link_id' => 41]);
        $operations->succeed($new, ['link_id' => 99]);
        $replay = $operations->reserve($owner->id, 'attach_document', 'action-1', $hash, $project['id']);
        $this->assertSame('replay', $replay->status);
        $this->assertSame(['link_id' => 41], $replay->result);

        $failed = $operations->reserve($owner->id, 'invoice_time', 'action-2', hash('sha256', 'b'), null);
        $operations->fail($failed, 'invoice_unavailable');
        $failedReplay = $operations->reserve($owner->id, 'invoice_time', 'action-2', hash('sha256', 'b'), null);
        $this->assertSame('failed', $failedReplay->status);
        $this->assertSame('invoice_unavailable', $failedReplay->errorCode);

        $foreignReservation = $operations->reserve(
            $foreign->id, 'attach_document', 'action-1', $hash, $foreignProject['id'],
        );
        $this->assertSame('new', $foreignReservation->status);

        try {
            $operations->reserve($owner->id, 'attach_document', 'action-1', hash('sha256', 'different'), $project['id']);
            $this->fail('A reused key with another request hash was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        $this->expectException(ModelNotFoundException::class);
        $operations->reserve($owner->id, 'foreign', 'action-3', hash('sha256', 'c'), $foreignProject['id']);
    }

    public function test_idempotency_key_is_bound_to_the_exact_nullable_project_target(): void
    {
        $owner = User::factory()->create();
        $first = $this->storedProject($owner, ['name' => 'First']);
        $second = $this->storedProject($owner, ['name' => 'Second']);
        $operations = app(ProjectOperationRepository::class);
        $hash = hash('sha256', 'same-body');

        $operations->reserve($owner->id, 'target-a', 'key-a', $hash, $first['id']);
        foreach ([null, $second['id']] as $differentTarget) {
            try {
                $operations->reserve($owner->id, 'target-a', 'key-a', $hash, $differentTarget);
                $this->fail('Idempotency key was replayed for another nullable project target.');
            } catch (DomainException $exception) {
                $this->assertSame('idempotency_key_reused', $exception->getMessage());
            }
        }

        $operations->reserve($owner->id, 'target-b', 'key-b', $hash, null);
        try {
            $operations->reserve($owner->id, 'target-b', 'key-b', $hash, $first['id']);
            $this->fail('A targetless idempotency key was rebound to a project.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        $sameTarget = $operations->reserve($owner->id, 'target-a', 'key-a', $hash, $first['id']);
        $this->assertSame('in_progress', $sameTarget->status);
    }

    public function test_reference_resolver_accepts_only_owned_opaque_legacy_references(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $now = '2026-08-28 10:00:00';
        $partnerId = DB::table('finance_partners')->insertGetId([
            'user_id' => $owner->id, 'name' => 'Partner', 'version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $methodId = DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id, 'type' => 'bank', 'name' => 'Bank',
            'business' => true, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $categoryId = DB::table('finance_categories')->insertGetId([
            'user_id' => $owner->id, 'name' => 'Travel', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $productId = DB::table('finance_products')->insertGetId([
            'user_id' => $owner->id, 'kind' => 'service', 'name' => 'Consulting',
            'price_net' => '100.00', 'active' => true, 'track_stock' => false,
            'stock_qty' => '0.000', 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $foreignPartnerId = DB::table('finance_partners')->insertGetId([
            'user_id' => $foreign->id, 'name' => 'Foreign', 'version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $resolver = app(ProjectReferenceResolver::class);

        $resolver->assertOwnedPartnerReference($owner->id, "legacy-partner:{$partnerId}");
        $resolver->assertOwnedPaymentMethodReference($owner->id, "legacy-payment-method:{$methodId}");
        $resolver->assertOwnedCategoryReference($owner->id, "legacy-category:{$categoryId}");
        $resolver->assertOwnedProductReference($owner->id, "legacy-product:{$productId}");
        $resolver->assertOwnedPartnerReference($owner->id, null);
        $this->addToAssertionCount(5);

        try {
            $resolver->assertOwnedPartnerReference($owner->id, "legacy-partner:{$foreignPartnerId}");
            $this->fail('A foreign partner reference was accepted.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        DB::table('finance_partners')->where('id', $partnerId)->update(['deleted_at' => $now]);
        try {
            $resolver->assertOwnedPartnerReference($owner->id, "legacy-partner:{$partnerId}");
            $this->fail('A deleted partner reference was accepted.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $resolver->assertOwnedPartnerReference($owner->id, (string) $partnerId);
    }

    public function test_provider_bindings_and_records_keep_server_owned_fields_out_of_mass_assignment(): void
    {
        $this->assertInstanceOf(EloquentProjectRepository::class, app(ProjectRepository::class));
        $this->assertInstanceOf(EloquentProjectOperationRepository::class, app(ProjectOperationRepository::class));
        $this->assertInstanceOf(LegacyProjectReferenceResolver::class, app(ProjectReferenceResolver::class));

        $cases = [
            [new ProjectRecord, ['user_id', 'uuid', 'parent_project_id', 'source_type', 'source_id', 'status', 'version', 'archived_at', 'created_by']],
            [new ProjectWorkItemRecord, ['user_id', 'project_id', 'uuid', 'status', 'source_revision_id', 'source_line_index', 'version', 'created_by']],
            [new ProjectTimeEntryRecord, ['user_id', 'project_id', 'work_item_id', 'uuid', 'invoice_target_reference', 'invoiced_at', 'version', 'created_by']],
            [new ProjectLedgerEntryRecord, ['user_id', 'project_id', 'uuid', 'legacy_metadata', 'version', 'created_by']],
            [new ProjectDocumentLinkRecord, ['user_id', 'project_id', 'source_reference', 'metadata_snapshot', 'attached_by', 'detached_by']],
            [new ProjectNoteRecord, ['user_id', 'project_id', 'body', 'supersedes_note_id', 'created_by']],
            [new ProjectActivityRecord, ['user_id', 'project_id', 'payload', 'created_by', 'occurred_at']],
            [new ProjectOperationRecord, ['user_id', 'project_id', 'request_sha256', 'state', 'result', 'error_code']],
        ];
        foreach ($cases as [$record, $guarded]) {
            foreach ($guarded as $field) {
                $this->assertNotContains($field, $record->getFillable(), $record::class.' '.$field);
            }
        }
    }

    public function test_project_notes_and_activities_reject_quiet_instance_and_bulk_mutations(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $this->storedNote($owner, $project['record_id'], 'Original note');
        $note = ProjectNoteRecord::query()->withoutGlobalScopes()->firstOrFail();
        $activityId = (int) DB::table('finance_project_activities')->insertGetId([
            'user_id' => $owner->id,
            'project_id' => $project['record_id'],
            'type' => 'project.created',
            'subject_type' => null,
            'subject_reference' => null,
            'payload' => json_encode(['name' => 'Project'], JSON_THROW_ON_ERROR),
            'created_by' => $owner->id,
            'occurred_at' => '2026-08-28 10:00:00',
            'created_at' => '2026-08-28 10:00:00',
        ]);
        $activity = ProjectActivityRecord::query()->withoutGlobalScopes()->findOrFail($activityId);

        $note->forceFill(['body' => 'Changed']);
        $this->assertAppendOnlyMutation('project_note', static fn (): bool => $note->saveQuietly());
        $this->assertAppendOnlyMutation('project_activity', static fn (): ?bool => $activity->deleteQuietly());
        $this->assertAppendOnlyMutation('project_note', static fn (): int => ProjectNoteRecord::query()
            ->withoutGlobalScopes()->whereKey($note->id)->update(['body' => 'Bulk changed']));
        $this->assertAppendOnlyMutation('project_activity', static fn (): mixed => ProjectActivityRecord::query()
            ->withoutGlobalScopes()->whereKey($activityId)->delete());

        $this->assertSame('Original note', DB::table('finance_project_notes')->where('id', $note->id)->value('body'));
        $this->assertTrue(DB::table('finance_project_activities')->where('id', $activityId)->exists());
    }

    public function test_document_link_is_immutable_except_for_one_way_detach_on_instance_and_bulk_paths(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner);
        $firstId = $this->storedDocumentLink($owner, $project['record_id'], 'file:first');
        $first = ProjectDocumentLinkRecord::query()->withoutGlobalScopes()->findOrFail($firstId);

        $first->forceFill([
            'detached_by' => $owner->id,
            'detached_at' => new DateTimeImmutable('2026-08-28 12:00:00'),
        ]);
        $this->assertTrue($first->saveQuietly());
        $first->forceFill(['source_reference' => 'file:retargeted']);
        $this->assertAppendOnlyMutation('project_document_link', static fn (): bool => $first->saveQuietly());
        $this->assertAppendOnlyMutation('project_document_link', static fn (): ?bool => $first->deleteQuietly());

        $secondId = $this->storedDocumentLink($owner, $project['record_id'], 'file:second');
        $updated = ProjectDocumentLinkRecord::query()
            ->withoutGlobalScopes()
            ->whereKey($secondId)
            ->update([
                'detached_by' => $owner->id,
                'detached_at' => new DateTimeImmutable('2026-08-28 13:00:00'),
            ]);
        $this->assertSame(1, $updated);
        $this->assertAppendOnlyMutation('project_document_link', static fn (): int => ProjectDocumentLinkRecord::query()
            ->withoutGlobalScopes()->whereKey($secondId)->update(['detached_at' => null]));

        $thirdId = $this->storedDocumentLink($owner, $project['record_id'], 'file:third');
        $this->assertAppendOnlyMutation('project_document_link', static fn (): int => ProjectDocumentLinkRecord::query()
            ->withoutGlobalScopes()->whereKey($thirdId)->update(['source_reference' => 'file:changed']));
        $this->assertSame('file:first', DB::table('finance_project_document_links')->where('id', $firstId)->value('source_reference'));
        $this->assertNotNull(DB::table('finance_project_document_links')->where('id', $firstId)->value('detached_at'));
        $this->assertNotNull(DB::table('finance_project_document_links')->where('id', $secondId)->value('detached_at'));
        $this->assertNull(DB::table('finance_project_document_links')->where('id', $thirdId)->value('detached_at'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{record_id: int, id: ProjectId}
     */
    private function storedProject(User $owner, array $overrides = []): array
    {
        $uuid = (string) ($overrides['uuid'] ?? Str::uuid());
        $now = (string) ($overrides['updated_at'] ?? '2026-08-28 09:00:00');
        $recordId = (int) DB::table('finance_project_records')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $uuid,
            'parent_project_id' => null,
            'source_type' => null,
            'source_id' => null,
            'name' => 'Project',
            'kind' => 'business',
            'status' => 'planned',
            'partner_reference' => null,
            'starts_on' => null,
            'due_on' => null,
            'budget_minor' => null,
            'currency' => 'EUR',
            'version' => 0,
            'archived_at' => null,
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
            ...$overrides,
        ]);

        return ['record_id' => $recordId, 'id' => new ProjectId($owner->id, $uuid)];
    }

    private function storedNote(User $owner, int $projectId, string $body): void
    {
        DB::table('finance_project_notes')->insert([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'type' => 'note',
            'visibility' => 'internal',
            'body' => $body,
            'supersedes_note_id' => null,
            'created_by' => $owner->id,
            'created_at' => '2026-08-28 10:00:00',
        ]);
    }

    private function storedDocumentLink(User $owner, int $projectId, string $reference): int
    {
        return (int) DB::table('finance_project_document_links')->insertGetId([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'source_type' => 'file',
            'source_reference' => $reference,
            'document_series_id' => null,
            'pinned_revision_id' => null,
            'role' => 'file',
            'metadata_snapshot' => json_encode(['name' => 'Evidence'], JSON_THROW_ON_ERROR),
            'attached_by' => $owner->id,
            'attached_at' => '2026-08-28 10:00:00',
            'detached_by' => null,
            'detached_at' => null,
        ]);
    }

    private function assertAppendOnlyMutation(string $kind, callable $mutation): void
    {
        try {
            $mutation();
            $this->fail("Append-only mutation {$kind} was accepted.");
        } catch (AppendOnlyRecordMutation $exception) {
            $this->assertSame($kind, $exception->kind);
        }
    }
}
