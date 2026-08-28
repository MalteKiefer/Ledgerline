<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\ArchiveProject;
use App\Modules\Finance\Application\Commands\Projects\ChangeProjectStatus;
use App\Modules\Finance\Application\Commands\Projects\CreateProject;
use App\Modules\Finance\Application\Commands\Projects\MoveProject;
use App\Modules\Finance\Application\Commands\Projects\RestoreProject;
use App\Modules\Finance\Application\Commands\Projects\UpdateProject;
use App\Modules\Finance\Application\DTOs\Projects\ChangeProjectStatusData;
use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\MoveProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectListFilter;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Queries\Projects\ListProjects;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectBudget;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProjectApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_valid_owned_data_and_appends_one_activity(): void
    {
        $owner = User::factory()->create();
        $partnerId = $this->storedPartner($owner, 'Owned partner');
        $parent = $this->storedProject($owner, ['name' => 'Parent']);
        $occurredAt = new DateTimeImmutable('2026-08-28 15:00:00');

        $created = app(CreateProject::class)->handle(new CreateProjectData(
            (int) $owner->id,
            '  Client launch  ',
            ProjectKind::Business,
            ProjectBudget::fromMinor(125_50, 'eur'),
            (int) $owner->id,
            $occurredAt,
            parentId: $parent['id'],
            partnerReference: "legacy-partner:{$partnerId}",
            startsOn: new DateTimeImmutable('2026-09-01'),
            dueOn: new DateTimeImmutable('2026-09-30'),
        ));

        $this->assertSame('Client launch', $created->name);
        $this->assertSame('EUR', $created->currency);
        $this->assertSame(125_50, $created->budgetMinor);
        $this->assertSame($parent['id']->uuid, $created->parentId?->uuid);
        $this->assertSame('2026-09-01', $created->startsOn?->format('Y-m-d'));
        $this->assertSame('2026-09-30', $created->dueOn?->format('Y-m-d'));
        $projectId = $this->internalProjectId($created->id);
        $activity = DB::table('finance_project_activities')->where('project_id', $projectId)->sole();
        $this->assertSame('project.created', $activity->type);
        $this->assertSame((int) $owner->id, $activity->user_id);
        $this->assertSame((int) $owner->id, $activity->created_by);
        $this->assertIsString($activity->payload);
        $this->assertSame(
            ['parent_uuid' => $parent['id']->uuid, 'status' => 'planned'],
            json_decode($activity->payload, true, flags: JSON_THROW_ON_ERROR),
        );
        $this->assertIsString($activity->occurred_at);
        $this->assertSame(
            '2026-08-28 15:00:00',
            new DateTimeImmutable($activity->occurred_at)->format('Y-m-d H:i:s'),
        );
    }

    public function test_create_rejects_blank_names_and_inverted_dates_without_writes(): void
    {
        $owner = User::factory()->create();
        $command = app(CreateProject::class);

        foreach ([
            new CreateProjectData(
                (int) $owner->id,
                " \t\n ",
                ProjectKind::Private,
                ProjectBudget::fromMinor(null, 'EUR'),
                (int) $owner->id,
                new DateTimeImmutable('2026-08-28 15:00:00'),
            ),
            new CreateProjectData(
                (int) $owner->id,
                'Inverted dates',
                ProjectKind::Private,
                ProjectBudget::fromMinor(0, 'EUR'),
                (int) $owner->id,
                new DateTimeImmutable('2026-08-28 15:00:00'),
                startsOn: new DateTimeImmutable('2026-10-02'),
                dueOn: new DateTimeImmutable('2026-10-01'),
            ),
        ] as $invalid) {
            try {
                $command->handle($invalid);
                $this->fail('Invalid project input was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('finance_project_records')->count());
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_create_rejects_foreign_references_archived_parents_and_actor_owner_mismatches(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $foreignPartner = $this->storedPartner($foreign, 'Foreign partner');
        $foreignParent = $this->storedProject($foreign, ['name' => 'Foreign parent']);
        $archivedParent = $this->storedProject($owner, [
            'name' => 'Archived parent',
            'archived_at' => '2026-08-28 14:00:00',
        ]);
        $command = app(CreateProject::class);
        $before = DB::table('finance_project_records')->count();

        $cases = [
            [
                new CreateProjectData(
                    (int) $owner->id,
                    'Foreign partner child',
                    ProjectKind::Business,
                    ProjectBudget::fromMinor(null, 'EUR'),
                    (int) $owner->id,
                    new DateTimeImmutable('2026-08-28 15:00:00'),
                    partnerReference: "legacy-partner:{$foreignPartner}",
                ),
                ModelNotFoundException::class,
                null,
            ],
            [
                new CreateProjectData(
                    (int) $owner->id,
                    'Foreign parent child',
                    ProjectKind::Business,
                    ProjectBudget::fromMinor(null, 'EUR'),
                    (int) $owner->id,
                    new DateTimeImmutable('2026-08-28 15:00:00'),
                    parentId: $foreignParent['id'],
                ),
                ModelNotFoundException::class,
                null,
            ],
            [
                new CreateProjectData(
                    (int) $owner->id,
                    'Archived parent child',
                    ProjectKind::Business,
                    ProjectBudget::fromMinor(null, 'EUR'),
                    (int) $owner->id,
                    new DateTimeImmutable('2026-08-28 15:00:00'),
                    parentId: $archivedParent['id'],
                ),
                InvalidProjectAction::class,
                'project_parent_archived',
            ],
            [
                new CreateProjectData(
                    (int) $owner->id,
                    'Wrong actor',
                    ProjectKind::Business,
                    ProjectBudget::fromMinor(null, 'EUR'),
                    (int) $foreign->id,
                    new DateTimeImmutable('2026-08-28 15:00:00'),
                ),
                InvalidArgumentException::class,
                null,
            ],
        ];

        foreach ($cases as [$data, $expectedClass, $expectedCode]) {
            try {
                $command->handle($data);
                $this->fail("Expected {$expectedClass}.");
            } catch (\Throwable $exception) {
                $this->assertInstanceOf($expectedClass, $exception);
                if ($expectedCode !== null) {
                    $this->assertSame($expectedCode, $exception->getMessage());
                }
            }
        }

        $this->assertSame($before, DB::table('finance_project_records')->count());
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_update_changes_only_editable_fields_and_returns_an_explicit_conflict_for_stale_versions(): void
    {
        $owner = User::factory()->create();
        $parent = $this->storedProject($owner, ['name' => 'Parent']);
        $project = $this->storedProject($owner, [
            'name' => 'Original',
            'parent_project_id' => $parent['record_id'],
            'source_type' => 'legacy.finance_project',
            'source_id' => 41,
            'status' => 'active',
        ]);
        $originalUuid = $project['id']->uuid;
        $this->storedActivity($owner, $project['record_id'], 'project.seeded', ['immutable' => true]);
        $command = app(UpdateProject::class);
        $occurredAt = new DateTimeImmutable('2026-08-28 16:00:00');

        $updated = $command->handle(new UpdateProjectData(
            $project['id'],
            0,
            '  Updated  ',
            ProjectKind::Private,
            ProjectBudget::fromMinor(99_99, 'usd'),
            (int) $owner->id,
            $occurredAt,
            startsOn: new DateTimeImmutable('2026-09-01'),
            dueOn: new DateTimeImmutable('2026-09-02'),
        ));

        $this->assertTrue($updated->applied);
        $this->assertSame('Updated', $updated->current->name);
        $this->assertSame('USD', $updated->current->currency);
        $row = DB::table('finance_project_records')->where('id', $project['record_id'])->sole();
        $this->assertSame($originalUuid, $row->uuid);
        $this->assertSame($parent['record_id'], $row->parent_project_id);
        $this->assertSame('legacy.finance_project', $row->source_type);
        $this->assertSame(41, $row->source_id);
        $this->assertSame('active', $row->status);
        $this->assertNull($row->archived_at);
        $this->assertSame(2, DB::table('finance_project_activities')->where('project_id', $project['record_id'])->count());
        $this->assertSame('project.seeded', DB::table('finance_project_activities')->where('project_id', $project['record_id'])->orderBy('id')->value('type'));

        $conflict = $command->handle(new UpdateProjectData(
            $project['id'],
            0,
            'Stale overwrite',
            ProjectKind::Business,
            ProjectBudget::fromMinor(1, 'EUR'),
            (int) $owner->id,
            $occurredAt,
        ));
        $this->assertFalse($conflict->applied);
        $this->assertSame('Updated', $conflict->current->name);
        $this->assertSame(1, $conflict->current->version);
        $this->assertSame(2, DB::table('finance_project_activities')->where('project_id', $project['record_id'])->count());
    }

    public function test_update_rejects_archived_projects_and_rolls_back_when_activity_append_fails(): void
    {
        $owner = User::factory()->create();
        $archived = $this->storedProject($owner, [
            'name' => 'Archived',
            'archived_at' => '2026-08-28 14:00:00',
        ]);
        $command = app(UpdateProject::class);
        try {
            $command->handle(new UpdateProjectData(
                $archived['id'],
                0,
                'Rejected',
                ProjectKind::Business,
                ProjectBudget::fromMinor(null, 'EUR'),
                (int) $owner->id,
                new DateTimeImmutable('2026-08-28 16:00:00'),
            ));
            $this->fail('Archived project was updated.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('project_archived', $exception->errorCode);
        }

        $active = $this->storedProject($owner, ['name' => 'Atomic original']);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER reject_project_updated_activity
            BEFORE INSERT ON finance_project_activities
            WHEN NEW.type = 'project.updated'
            BEGIN
                SELECT RAISE(ABORT, 'activity rejected');
            END
            SQL);
        try {
            $command->handle(new UpdateProjectData(
                $active['id'],
                0,
                'Must roll back',
                ProjectKind::Private,
                ProjectBudget::fromMinor(10, 'EUR'),
                (int) $owner->id,
                new DateTimeImmutable('2026-08-28 16:00:00'),
            ));
            $this->fail('Update survived an activity append failure.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('activity rejected', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_project_updated_activity');
        }

        $row = DB::table('finance_project_records')->where('id', $active['record_id'])->sole();
        $this->assertSame('Atomic original', $row->name);
        $this->assertSame(0, $row->version);
        $this->assertSame(0, DB::table('finance_project_activities')->where('project_id', $active['record_id'])->count());
    }

    public function test_update_rejects_a_foreign_actor_before_disclosing_a_version_conflict(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $project = $this->storedProject($owner, ['name' => 'Private', 'version' => 4]);

        try {
            app(UpdateProject::class)->handle(new UpdateProjectData(
                $project['id'],
                0,
                'Forbidden',
                ProjectKind::Private,
                ProjectBudget::fromMinor(null, 'EUR'),
                (int) $foreign->id,
                new DateTimeImmutable('2026-08-28 16:00:00'),
            ));
            $this->fail('A foreign actor received project conflict details.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Project actor must match owner.', $exception->getMessage());
        }

        $this->assertSame('Private', DB::table('finance_project_records')->where('id', $project['record_id'])->value('name'));
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    #[DataProvider('projectTransitions')]
    public function test_status_command_applies_every_ordinary_and_reopen_transition_with_history(
        ProjectStatus $from,
        ProjectStatus $to,
        bool $reopened,
    ): void {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner, ['status' => $from->value]);

        $result = app(ChangeProjectStatus::class)->handle(new ChangeProjectStatusData(
            $project['id'],
            0,
            $to,
            (int) $owner->id,
            new DateTimeImmutable('2026-08-28 17:00:00'),
        ));

        $this->assertTrue($result->applied);
        $this->assertSame($to, $result->current->status);
        $this->assertSame(1, $result->current->version);
        $activity = DB::table('finance_project_activities')->where('project_id', $project['record_id'])->sole();
        $this->assertSame('project.status_changed', $activity->type);
        $this->assertIsString($activity->payload);
        $this->assertSame([
            'new_status' => $to->value,
            'old_status' => $from->value,
            'reopened' => $reopened,
        ], json_decode($activity->payload, true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{ProjectStatus, ProjectStatus, bool}> */
    public static function projectTransitions(): iterable
    {
        yield 'planned to active' => [ProjectStatus::Planned, ProjectStatus::Active, false];
        yield 'planned to cancelled' => [ProjectStatus::Planned, ProjectStatus::Cancelled, false];
        yield 'active to on hold' => [ProjectStatus::Active, ProjectStatus::OnHold, false];
        yield 'active to done' => [ProjectStatus::Active, ProjectStatus::Done, false];
        yield 'active to cancelled' => [ProjectStatus::Active, ProjectStatus::Cancelled, false];
        yield 'on hold to active' => [ProjectStatus::OnHold, ProjectStatus::Active, false];
        yield 'on hold to cancelled' => [ProjectStatus::OnHold, ProjectStatus::Cancelled, false];
        yield 'done reopen' => [ProjectStatus::Done, ProjectStatus::Active, true];
        yield 'cancelled reopen' => [ProjectStatus::Cancelled, ProjectStatus::Planned, true];
    }

    public function test_status_command_rejects_invalid_or_archived_changes_and_stale_retries_are_side_effect_free(): void
    {
        $owner = User::factory()->create();
        $planned = $this->storedProject($owner, ['name' => 'Planned']);
        $archived = $this->storedProject($owner, [
            'name' => 'Archived',
            'status' => 'active',
            'archived_at' => '2026-08-28 16:00:00',
        ]);
        $current = $this->storedProject($owner, [
            'name' => 'Current',
            'status' => 'active',
            'version' => 1,
        ]);
        $command = app(ChangeProjectStatus::class);

        foreach ([
            [
                new ChangeProjectStatusData($planned['id'], 0, ProjectStatus::Done, (int) $owner->id, new DateTimeImmutable),
                'invalid_transition',
            ],
            [
                new ChangeProjectStatusData($archived['id'], 0, ProjectStatus::Done, (int) $owner->id, new DateTimeImmutable),
                'project_archived',
            ],
        ] as [$data, $code]) {
            try {
                $command->handle($data);
                $this->fail("Status change {$code} was accepted.");
            } catch (InvalidProjectAction $exception) {
                $this->assertSame($code, $exception->errorCode);
            }
        }

        foreach ([1, 2] as $attempt) {
            $conflict = $command->handle(new ChangeProjectStatusData(
                $current['id'],
                0,
                ProjectStatus::Done,
                (int) $owner->id,
                new DateTimeImmutable('2026-08-28 17:00:00'),
            ));
            $this->assertFalse($conflict->applied, (string) $attempt);
            $this->assertSame(1, $conflict->current->version);
        }
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_move_uses_the_atomic_hierarchy_operation_and_records_old_and_new_parents(): void
    {
        $owner = User::factory()->create();
        $firstParent = $this->storedProject($owner, ['name' => 'First parent']);
        $secondParent = $this->storedProject($owner, ['name' => 'Second parent']);
        $child = $this->storedProject($owner, [
            'name' => 'Child',
            'parent_project_id' => $firstParent['record_id'],
        ]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = app(MoveProject::class)->handle(new MoveProjectData(
            $child['id'],
            0,
            $secondParent['id'],
            (int) $owner->id,
            new DateTimeImmutable('2026-08-28 18:00:00'),
        ));

        $this->assertTrue($result->applied);
        $this->assertSame($secondParent['id']->uuid, $result->current->parentId?->uuid);
        $activity = DB::table('finance_project_activities')->where('project_id', $child['record_id'])->sole();
        $this->assertSame('project.moved', $activity->type);
        $this->assertIsString($activity->payload);
        $this->assertSame([
            'new_parent_uuid' => $secondParent['id']->uuid,
            'old_parent_uuid' => $firstParent['id']->uuid,
        ], json_decode($activity->payload, true, flags: JSON_THROW_ON_ERROR));
        $lockQuery = collect(DB::getQueryLog())->first(static fn (array $query): bool => str_contains((string) $query['query'], 'finance_project_records')
            && str_contains((string) $query['query'], 'order by'));
        $this->assertIsArray($lockQuery);
        $this->assertMatchesRegularExpression('/order by ["`]id["`] asc/i', (string) $lockQuery['query']);
    }

    public function test_move_rejects_foreign_archived_self_deep_and_opposite_hierarchies_without_history(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $parent = $this->storedProject($owner, ['name' => 'Parent']);
        $child = $this->storedProject($owner, [
            'name' => 'Child',
            'parent_project_id' => $parent['record_id'],
        ]);
        $grandchild = $this->storedProject($owner, [
            'name' => 'Grandchild',
            'parent_project_id' => $child['record_id'],
        ]);
        $archived = $this->storedProject($owner, [
            'name' => 'Archived parent',
            'archived_at' => '2026-08-28 16:00:00',
        ]);
        $foreignParent = $this->storedProject($foreign, ['name' => 'Foreign parent']);
        $command = app(MoveProject::class);
        $at = new DateTimeImmutable('2026-08-28 18:00:00');

        foreach ([
            [new MoveProjectData($parent['id'], 0, $parent['id'], (int) $owner->id, $at), InvalidProjectAction::class, 'project_parent_cycle'],
            [new MoveProjectData($parent['id'], 0, $grandchild['id'], (int) $owner->id, $at), InvalidProjectAction::class, 'project_parent_cycle'],
            [new MoveProjectData($grandchild['id'], 0, $archived['id'], (int) $owner->id, $at), InvalidProjectAction::class, 'project_parent_archived'],
            [new MoveProjectData($grandchild['id'], 0, $foreignParent['id'], (int) $owner->id, $at), ModelNotFoundException::class, null],
        ] as [$data, $class, $code]) {
            try {
                $command->handle($data);
                $this->fail("Invalid move {$class} was accepted.");
            } catch (\Throwable $exception) {
                $this->assertInstanceOf($class, $exception);
                if ($code !== null) {
                    $this->assertSame($code, $exception->getMessage());
                }
            }
        }

        $left = $this->storedProject($owner, ['name' => 'Left']);
        $right = $this->storedProject($owner, ['name' => 'Right']);
        $this->assertTrue($command->handle(new MoveProjectData(
            $left['id'], 0, $right['id'], (int) $owner->id, $at,
        ))->applied);
        try {
            $command->handle(new MoveProjectData(
                $right['id'], 0, $left['id'], (int) $owner->id, $at,
            ));
            $this->fail('Opposite move created a cycle.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('project_parent_cycle', $exception->errorCode);
        }
        $this->assertSame(1, DB::table('finance_project_activities')->count());
        $this->assertNull(DB::table('finance_project_records')->where('id', $right['record_id'])->value('parent_project_id'));
    }

    public function test_move_rejects_archived_projects_and_returns_conflicts_without_activity(): void
    {
        $owner = User::factory()->create();
        $parent = $this->storedProject($owner, ['name' => 'Parent']);
        $archived = $this->storedProject($owner, [
            'name' => 'Archived child',
            'archived_at' => '2026-08-28 16:00:00',
        ]);
        $current = $this->storedProject($owner, ['name' => 'Versioned', 'version' => 2]);
        $command = app(MoveProject::class);

        try {
            $command->handle(new MoveProjectData(
                $archived['id'], 0, $parent['id'], (int) $owner->id, new DateTimeImmutable,
            ));
            $this->fail('Archived project was moved.');
        } catch (InvalidProjectAction $exception) {
            $this->assertSame('project_archived', $exception->errorCode);
        }

        $conflict = $command->handle(new MoveProjectData(
            $current['id'], 1, $parent['id'], (int) $owner->id, new DateTimeImmutable,
        ));
        $this->assertFalse($conflict->applied);
        $this->assertSame(2, $conflict->current->version);
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_archive_and_restore_preserve_the_complete_project_graph_and_append_history(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner, ['name' => 'Lifecycle']);
        $child = $this->storedProject($owner, [
            'name' => 'Child',
            'parent_project_id' => $project['record_id'],
        ]);
        $this->storedProjectGraph($owner, $project['record_id']);
        $archiveAt = new DateTimeImmutable('2026-08-28 19:00:00');

        $archived = app(ArchiveProject::class)->handle(
            $project['id'], 0, (int) $owner->id, $archiveAt,
        );

        $this->assertTrue($archived->applied);
        $this->assertTrue($archived->current->archived);
        $this->assertSame(1, $archived->current->version);
        $normal = app(ListProjects::class)->handle(new ProjectListFilter((int) $owner->id));
        $this->assertNotContains($project['id']->uuid, array_map(
            static fn ($view): string => $view->id->uuid,
            $normal->items,
        ));
        $this->assertFalse(app(ProjectRepository::class)
            ->get($child['id'])->parentAvailable);
        $this->assertProjectGraphCounts($project['record_id']);
        $this->assertSame($project['record_id'], DB::table('finance_project_records')->where('id', $child['record_id'])->value('parent_project_id'));

        $restored = app(RestoreProject::class)->handle(
            $project['id'], 1, (int) $owner->id, new DateTimeImmutable('2026-08-28 20:00:00'),
        );
        $this->assertTrue($restored->applied);
        $this->assertFalse($restored->current->archived);
        $this->assertSame($project['id']->uuid, $restored->current->id->uuid);
        $this->assertTrue(app(ProjectRepository::class)
            ->get($child['id'])->parentAvailable);
        $this->assertProjectGraphCounts($project['record_id']);
        $this->assertSame(
            ['project.seeded', 'project.archived', 'project.restored'],
            DB::table('finance_project_activities')
                ->where('project_id', $project['record_id'])
                ->orderBy('id')
                ->pluck('type')
                ->all(),
        );
    }

    public function test_archive_and_restore_return_explicit_conflicts_without_duplicate_history(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner, ['name' => 'Versioned', 'version' => 2]);

        $archive = app(ArchiveProject::class)->handle(
            $project['id'], 1, (int) $owner->id, new DateTimeImmutable,
        );
        $restore = app(RestoreProject::class)->handle(
            $project['id'], 1, (int) $owner->id, new DateTimeImmutable,
        );

        $this->assertFalse($archive->applied);
        $this->assertFalse($restore->applied);
        $this->assertSame(2, $archive->current->version);
        $this->assertSame(2, $restore->current->version);
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_archive_and_restore_are_idempotent_when_already_in_the_requested_state(): void
    {
        $owner = User::factory()->create();
        $archived = $this->storedProject($owner, [
            'name' => 'Already archived',
            'version' => 3,
            'archived_at' => '2026-08-28 14:00:00',
        ]);
        $active = $this->storedProject($owner, ['name' => 'Already active', 'version' => 5]);

        $archiveResult = app(ArchiveProject::class)->handle(
            $archived['id'], 3, (int) $owner->id, new DateTimeImmutable('2026-08-28 19:00:00'),
        );
        $restoreResult = app(RestoreProject::class)->handle(
            $active['id'], 5, (int) $owner->id, new DateTimeImmutable('2026-08-28 20:00:00'),
        );

        $this->assertTrue($archiveResult->applied);
        $this->assertTrue($archiveResult->current->archived);
        $this->assertSame(3, $archiveResult->current->version);
        $this->assertTrue($restoreResult->applied);
        $this->assertFalse($restoreResult->current->archived);
        $this->assertSame(5, $restoreResult->current->version);
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_archive_same_state_decision_cannot_be_invalidated_before_the_repository_lock(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner, [
            'name' => 'Archived concurrently',
            'version' => 3,
            'archived_at' => '2026-08-28 14:00:00',
        ]);
        $interleaved = false;
        $outsideTransactionLevel = DB::transactionLevel();
        DB::listen(function (QueryExecuted $query) use (&$interleaved, $outsideTransactionLevel, $project): void {
            if ($interleaved || DB::transactionLevel() !== $outsideTransactionLevel || ! str_contains($query->sql, 'finance_project_records')) {
                return;
            }

            $interleaved = true;
            DB::table('finance_project_records')->where('id', $project['record_id'])->update([
                'archived_at' => null,
                'version' => 4,
            ]);
        });

        $result = app(ArchiveProject::class)->handle(
            $project['id'], 3, (int) $owner->id, new DateTimeImmutable('2026-08-28 19:00:00'),
        );
        $interleaved = true;
        $persisted = DB::table('finance_project_records')->where('id', $project['record_id'])->sole();

        $this->assertIsInt($persisted->version);
        $this->assertSame($persisted->version, $result->current->version);
        $this->assertSame($persisted->archived_at !== null, $result->current->archived);
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    public function test_restore_same_state_decision_cannot_be_invalidated_before_the_repository_lock(): void
    {
        $owner = User::factory()->create();
        $project = $this->storedProject($owner, [
            'name' => 'Restored concurrently',
            'version' => 3,
        ]);
        $interleaved = false;
        $outsideTransactionLevel = DB::transactionLevel();
        DB::listen(function (QueryExecuted $query) use (&$interleaved, $outsideTransactionLevel, $project): void {
            if ($interleaved || DB::transactionLevel() !== $outsideTransactionLevel || ! str_contains($query->sql, 'finance_project_records')) {
                return;
            }

            $interleaved = true;
            DB::table('finance_project_records')->where('id', $project['record_id'])->update([
                'archived_at' => '2026-08-28 14:30:00',
                'version' => 4,
            ]);
        });

        $result = app(RestoreProject::class)->handle(
            $project['id'], 3, (int) $owner->id, new DateTimeImmutable('2026-08-28 20:00:00'),
        );
        $interleaved = true;
        $persisted = DB::table('finance_project_records')->where('id', $project['record_id'])->sole();

        $this->assertIsInt($persisted->version);
        $this->assertSame($persisted->version, $result->current->version);
        $this->assertSame($persisted->archived_at !== null, $result->current->archived);
        $this->assertSame(0, DB::table('finance_project_activities')->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{record_id: int, id: ProjectId}
     */
    private function storedProject(User $owner, array $overrides = []): array
    {
        $uuid = (string) Str::uuid();
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
            'created_at' => '2026-08-28 14:00:00',
            'updated_at' => '2026-08-28 14:00:00',
            ...$overrides,
        ]);

        return ['record_id' => $recordId, 'id' => new ProjectId((int) $owner->id, $uuid)];
    }

    private function storedPartner(User $owner, string $name): int
    {
        return (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $owner->id,
            'name' => $name,
            'version' => 0,
            'created_at' => '2026-08-28 14:00:00',
            'updated_at' => '2026-08-28 14:00:00',
        ]);
    }

    private function storedProjectGraph(User $owner, int $projectId): void
    {
        $now = '2026-08-28 14:00:00';
        DB::table('finance_project_work_items')->insert([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'uuid' => (string) Str::uuid(),
            'title' => 'Work',
            'description' => null,
            'status' => 'open',
            'starts_on' => null,
            'due_on' => null,
            'estimate_quantity_scaled' => null,
            'is_milestone' => false,
            'sort' => 0,
            'source_revision_id' => null,
            'source_line_index' => null,
            'product_reference' => null,
            'version' => 0,
            'created_by' => $owner->id,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('finance_project_time_entries')->insert([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'work_item_id' => null,
            'uuid' => (string) Str::uuid(),
            'worked_on' => '2026-08-28',
            'quantity_scaled' => 10_000,
            'description' => 'Time',
            'billable' => true,
            'hourly_rate_minor' => 10_000,
            'currency' => 'EUR',
            'invoice_target_reference' => null,
            'invoiced_at' => null,
            'version' => 0,
            'created_by' => $owner->id,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('finance_project_ledger_entries')->insert([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'uuid' => (string) Str::uuid(),
            'direction' => 'out',
            'amount_minor' => 500,
            'currency' => 'EUR',
            'occurred_on' => '2026-08-28',
            'title' => 'Expense',
            'note' => null,
            'category_reference' => null,
            'payment_method_reference' => null,
            'legacy_metadata' => null,
            'version' => 0,
            'created_by' => $owner->id,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('finance_project_document_links')->insert([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'source_type' => 'file',
            'source_reference' => 'file:lifecycle',
            'document_series_id' => null,
            'pinned_revision_id' => null,
            'role' => 'file',
            'metadata_snapshot' => json_encode(['name' => 'Evidence'], JSON_THROW_ON_ERROR),
            'attached_by' => $owner->id,
            'attached_at' => $now,
            'detached_by' => null,
            'detached_at' => null,
        ]);
        DB::table('finance_project_notes')->insert([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'type' => 'note',
            'visibility' => 'internal',
            'body' => 'History',
            'supersedes_note_id' => null,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
        $this->storedActivity($owner, $projectId, 'project.seeded', ['immutable' => true]);
    }

    private function assertProjectGraphCounts(int $projectId): void
    {
        foreach ([
            'finance_project_work_items',
            'finance_project_time_entries',
            'finance_project_ledger_entries',
            'finance_project_document_links',
            'finance_project_notes',
        ] as $table) {
            $this->assertSame(1, DB::table($table)->where('project_id', $projectId)->count(), $table);
        }
    }

    /** @param array<string, mixed> $payload */
    private function storedActivity(User $owner, int $projectId, string $type, array $payload): int
    {
        return (int) DB::table('finance_project_activities')->insertGetId([
            'user_id' => $owner->id,
            'project_id' => $projectId,
            'type' => $type,
            'subject_type' => null,
            'subject_reference' => null,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_by' => $owner->id,
            'occurred_at' => '2026-08-28 14:00:00',
            'created_at' => '2026-08-28 14:00:00',
        ]);
    }

    private function internalProjectId(ProjectId $id): int
    {
        $internalId = DB::table('finance_project_records')
            ->where('user_id', $id->ownerId)
            ->where('uuid', $id->uuid)
            ->value('id');
        $this->assertIsInt($internalId);

        return $internalId;
    }
}
