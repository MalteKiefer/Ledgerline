<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Recurring\AddRecurringInvoiceTemplateVersion;
use App\Modules\Finance\Application\Commands\Recurring\CreateRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Commands\Recurring\PauseRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Commands\Recurring\ResumeRecurringInvoiceTemplate;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionConflict;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use App\Modules\Finance\Domain\Recurring\RecurrenceInterval;
use App\Modules\Finance\Domain\Shared\Discount;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class RecurringTemplateApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_version_one_exact_snapshot_and_local_start_as_utc(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $template = $this->create()->handle(
            new RecurringTemplateData(
                mode: 'auto_send',
                interval: RecurrenceInterval::Monthly,
                timezone: 'Europe/Berlin',
                startDate: new DateTimeImmutable('2026-03-29'),
                endDate: new DateTimeImmutable('2026-06-30'),
                runTime: '02:30:00',
                initialVersion: new RecurringTemplateVersionData(
                    new DateTimeImmutable('2026-03-29'),
                    $this->draft(),
                ),
            ),
            new IdempotencyKey('recurring-create-exact'),
        );

        $this->assertSame(1, $template->currentVersionNumber);
        $this->assertSame(0, $template->version);
        $this->assertSame('2026-03-29T01:30:00+00:00', $template->nextRunAt->format(DATE_ATOM));
        $this->assertSame(29, $template->anchorDay);
        $this->assertFalse($template->monthEndAnchor);

        $versionQuery = DB::table('finance_recurring_invoice_template_versions')
            ->where('id', $template->currentVersionId);
        $draftSnapshot = $versionQuery->value('draft_snapshot');
        $this->assertIsString($draftSnapshot);
        $snapshot = json_decode($draftSnapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($snapshot);
        $lines = $snapshot['lines'] ?? null;
        $totals = $snapshot['totals'] ?? null;
        $this->assertIsArray($lines);
        $this->assertIsArray($totals);
        $line = $lines[0] ?? null;
        $this->assertIsArray($line);
        $this->assertSame('2.5000', $line['quantity'] ?? null);
        $this->assertSame(25_000, $totals['net_minor'] ?? null);
        $this->assertSame(4_750, $totals['vat_minor'] ?? null);
        $this->assertSame(29_750, $totals['gross_minor'] ?? null);
        $snapshotSha256 = $versionQuery->value('snapshot_sha256');
        $createdBy = $versionQuery->value('created_by');
        $this->assertIsString($snapshotSha256);
        $this->assertIsInt($createdBy);
        $this->assertSame(
            hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            $snapshotSha256,
        );
        $this->assertSame($owner->id, $createdBy);
    }

    public function test_create_rejects_invalid_schedule_and_control_totals_before_persistence(): void
    {
        foreach ([
            fn (): RecurringTemplateData => new RecurringTemplateData(
                'draft', RecurrenceInterval::Monthly, '+01:00',
                new DateTimeImmutable('2026-01-01'), null, '08:00:00',
                new RecurringTemplateVersionData(new DateTimeImmutable('2026-01-01'), $this->draft()),
            ),
            fn (): RecurringTemplateData => new RecurringTemplateData(
                'draft', RecurrenceInterval::Monthly, 'Europe/Berlin',
                new DateTimeImmutable('2026-02-01'), new DateTimeImmutable('2026-01-31'), '08:00:00',
                new RecurringTemplateVersionData(new DateTimeImmutable('2026-02-01'), $this->draft()),
            ),
            fn (): RecurringTemplateData => new RecurringTemplateData(
                'draft', RecurrenceInterval::Monthly, 'Europe/Berlin',
                new DateTimeImmutable('2026-01-01'), null, '8:00',
                new RecurringTemplateVersionData(new DateTimeImmutable('2026-01-01'), $this->draft()),
            ),
            fn (): RecurringTemplateData => new RecurringTemplateData(
                'draft', RecurrenceInterval::Monthly, 'Europe/Berlin',
                new DateTimeImmutable('2026-01-01'), null, '08:00:00',
                new RecurringTemplateVersionData(new DateTimeImmutable('2025-12-31'), $this->draft()),
            ),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('An invalid recurring template schedule was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            new RecurringTemplateVersionData(
                new DateTimeImmutable('2026-01-01'),
                $this->draft(controlGrossMinor: 29_751),
            );
            $this->fail('A mismatching recurring invoice control total was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('document_totals_mismatch', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_recurring_invoice_templates', 0);
        $this->assertDatabaseCount('finance_idempotency_records', 0);
    }

    public function test_new_operations_validate_owner_scoped_partner_project_and_product_references(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $foreignPartner = $this->partner((int) $foreign->id);
        $foreignProject = $this->project((int) $foreign->id);
        $foreignProduct = $this->product((int) $foreign->id);
        $this->actingAs($owner);

        foreach ([
            $this->draft(partnerId: $foreignPartner),
            $this->draft(projectId: $foreignProject),
            $this->draft(productId: $foreignProduct),
        ] as $index => $draft) {
            try {
                $this->create()->handle(
                    $this->templateData($draft),
                    new IdempotencyKey('recurring-owner-'.$index),
                );
                $this->fail('A foreign recurring invoice reference was accepted.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('finance_recurring_invoice_templates', 0);
        $this->assertDatabaseCount('finance_idempotency_records', 0);
    }

    public function test_versions_are_effective_dated_selected_deterministically_and_do_not_retarget_runs(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->create()->handle(
            $this->templateData($this->draft()),
            new IdempotencyKey('recurring-effective-create'),
        );
        $versions = $this->addVersion();
        $march = $versions->handle(
            $created->id,
            new RecurringTemplateVersionData(new DateTimeImmutable('2026-03-01'), $this->draft(unitPriceMinor: 20_000, controlGrossMinor: null)),
            expectedVersion: 0,
            key: new IdempotencyKey('recurring-effective-march'),
        );
        $marchVersionId = $march->currentVersionId;

        $runId = DB::table('finance_recurring_invoice_runs')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => '018f4ca3-224d-7d8d-9f99-000000000001',
            'template_id' => $created->id->value,
            'template_version_id' => $marchVersionId,
            'scheduled_for' => '2026-04-01 06:00:00',
            'scheduled_local_date' => '2026-04-01',
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key_hash' => hash('sha256', 'recurring-effective-run'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $june = $versions->handle(
            $created->id,
            new RecurringTemplateVersionData(new DateTimeImmutable('2026-06-01'), $this->draft(unitPriceMinor: 30_000, controlGrossMinor: null)),
            expectedVersion: 1,
            key: new IdempotencyKey('recurring-effective-june'),
        );
        $repository = app(RecurringInvoiceRepository::class);

        $this->assertSame(1, $repository->versionForOccurrence($created->id, new DateTimeImmutable('2026-02-28'))['version_number']);
        $this->assertSame(2, $repository->versionForOccurrence($created->id, new DateTimeImmutable('2026-03-01'))['version_number']);
        $this->assertSame(3, $repository->versionForOccurrence($created->id, new DateTimeImmutable('2026-06-01'))['version_number']);
        $this->assertSame(
            $marchVersionId,
            DB::table('finance_recurring_invoice_runs')->where('id', $runId)->value('template_version_id'),
        );
        $this->assertSame(3, $june->currentVersionNumber);
        $this->assertDatabaseCount('finance_recurring_invoice_template_versions', 3);
    }

    public function test_pause_resume_preserve_next_due_and_stale_conflict_carries_current_template(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->create()->handle(
            $this->templateData($this->draft()),
            new IdempotencyKey('recurring-state-create'),
        );
        $nextRunAt = $created->nextRunAt->format('Y-m-d H:i:s.uP');
        $paused = $this->pause()->handle($created->id, 0, new IdempotencyKey('recurring-state-pause'));

        $this->assertSame('paused', $paused->status);
        $this->assertSame($nextRunAt, $paused->nextRunAt->format('Y-m-d H:i:s.uP'));
        $this->assertNotNull($paused->pausedAt);

        try {
            $this->resume()->handle($created->id, 0, new IdempotencyKey('recurring-state-stale'));
            $this->fail('A stale recurring template version was accepted.');
        } catch (RecurringTemplateVersionConflict $exception) {
            $this->assertSame('recurring_template_version_conflict', $exception->getMessage());
            $this->assertSame(1, $exception->current->version);
            $this->assertSame('paused', $exception->current->status);
        }

        $resumed = $this->resume()->handle($created->id, 1, new IdempotencyKey('recurring-state-resume'));
        $this->assertSame('active', $resumed->status);
        $this->assertNull($resumed->pausedAt);
        $this->assertSame($nextRunAt, $resumed->nextRunAt->format('Y-m-d H:i:s.uP'));
    }

    public function test_replay_precedes_mutable_preflights_but_changed_payload_conflicts(): void
    {
        $owner = User::factory()->create();
        $partnerId = $this->partner((int) $owner->id);
        $this->actingAs($owner);
        $createKey = new IdempotencyKey('recurring-replay-create');
        $data = $this->templateData($this->draft(partnerId: $partnerId));
        $created = $this->create()->handle($data, $createKey);
        $versionKey = new IdempotencyKey('recurring-replay-version');
        $versionData = new RecurringTemplateVersionData(
            new DateTimeImmutable('2026-03-01'),
            $this->draft(partnerId: $partnerId, unitPriceMinor: 20_000, controlGrossMinor: null),
        );
        $versioned = $this->addVersion()->handle($created->id, $versionData, 0, $versionKey);
        $pauseKey = new IdempotencyKey('recurring-replay-pause');
        $paused = $this->pause()->handle($created->id, 1, $pauseKey);
        $this->resume()->handle($created->id, 2, new IdempotencyKey('recurring-replay-resume'));
        DB::table('finance_partners')->where('id', $partnerId)->update(['deleted_at' => now()]);

        $this->assertEquals($created, $this->create()->handle($data, $createKey));
        $this->assertEquals($versioned, $this->addVersion()->handle($created->id, $versionData, 0, $versionKey));
        $this->assertEquals($paused, $this->pause()->handle($created->id, 1, $pauseKey));

        try {
            $this->addVersion()->handle(
                $created->id,
                new RecurringTemplateVersionData(new DateTimeImmutable('2026-04-01'), $this->draft()),
                0,
                $versionKey,
            );
            $this->fail('A recurring idempotency key was reused with a changed caller intent.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        try {
            $this->addVersion()->handle($created->id, $versionData, 1, $versionKey);
            $this->fail('A changed expected version reused a recurring idempotency key.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        $this->assertSame(
            3,
            DB::table('finance_recurring_invoice_templates')->where('id', $created->id->value)->value('version'),
        );
        $this->assertDatabaseCount('finance_recurring_invoice_template_versions', 2);
    }

    public function test_pending_same_key_does_not_execute_a_second_mutation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->create()->handle(
            $this->templateData($this->draft()),
            new IdempotencyKey('recurring-pending-create'),
        );
        $key = new IdempotencyKey('recurring-pending-pause');
        $requestHash = hash('sha256', json_encode([
            'template_id' => $created->id->value,
            'expected_version' => 0,
            'action' => 'pause',
        ], JSON_THROW_ON_ERROR));
        DB::table('finance_idempotency_records')->insert([
            'user_id' => $owner->id,
            'operation' => 'recurring.template.pause',
            'key_hash' => $key->hash(),
            'request_hash' => $requestHash,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->pause()->handle($created->id, 0, $key);
            $this->fail('A pending same-key recurring operation executed twice.');
        } catch (DomainException $exception) {
            $this->assertSame('operation_in_progress', $exception->getMessage());
        }

        $row = DB::table('finance_recurring_invoice_templates')->where('id', $created->id->value);
        $this->assertSame('active', $row->value('status'));
        $this->assertSame(0, $row->value('version'));
    }

    public function test_postgresql_concurrent_version_writers_have_one_cas_winner_when_configured(): void
    {
        $this->withIsolatedPostgresSchema(function (string $postgresUrl, string $schema): void {
            $owner = new User;
            $owner->forceFill(['id' => 1]);
            $this->actingAs($owner);
            $created = $this->create()->handle(
                $this->templateData($this->draft()),
                new IdempotencyKey('recurring-pg-create'),
            );
            DB::statement('CREATE TABLE finance_task12_version_barrier (worker varchar(32) PRIMARY KEY)');
            $processes = [
                $this->startPostgresVersionWorker($postgresUrl, $schema, 'first', $created->id->value, '2026-03-01'),
                $this->startPostgresVersionWorker($postgresUrl, $schema, 'second', $created->id->value, '2026-04-01'),
            ];
            $results = [];

            foreach ($processes as $process) {
                $exitCode = $process->wait();
                $this->assertSame(0, $exitCode, $process->getErrorOutput().$process->getOutput());
                $decoded = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($decoded);
                $results[] = $decoded['result'] ?? null;
            }

            sort($results);
            $this->assertSame(['conflict', 'ok'], $results);
            $this->assertSame(
                1,
                DB::table('finance_recurring_invoice_templates')->where('id', $created->id->value)->value('version'),
            );
            $this->assertSame(2, DB::table('finance_recurring_invoice_template_versions')->count());
        });
    }

    private function create(): CreateRecurringInvoiceTemplate
    {
        return app(CreateRecurringInvoiceTemplate::class);
    }

    private function addVersion(): AddRecurringInvoiceTemplateVersion
    {
        return app(AddRecurringInvoiceTemplateVersion::class);
    }

    private function pause(): PauseRecurringInvoiceTemplate
    {
        return app(PauseRecurringInvoiceTemplate::class);
    }

    private function resume(): ResumeRecurringInvoiceTemplate
    {
        return app(ResumeRecurringInvoiceTemplate::class);
    }

    private function templateData(InvoiceDraftData $draft): RecurringTemplateData
    {
        return new RecurringTemplateData(
            mode: 'draft',
            interval: RecurrenceInterval::Monthly,
            timezone: 'Europe/Berlin',
            startDate: new DateTimeImmutable('2026-01-31'),
            endDate: null,
            runTime: '08:00:00',
            initialVersion: new RecurringTemplateVersionData(new DateTimeImmutable('2026-01-31'), $draft),
        );
    }

    private function draft(
        ?int $partnerId = null,
        ?int $projectId = null,
        ?int $productId = null,
        int $unitPriceMinor = 10_000,
        ?int $controlGrossMinor = 29_750,
    ): InvoiceDraftData {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-01-31'),
            dueDate: new DateTimeImmutable('2026-02-14'),
            currency: 'EUR',
            customer: ['name' => 'ACME', 'address' => ['city' => 'Berlin', 'postcode' => '10115']],
            lines: [new InvoiceLineData('Work', '2.5000', $unitPriceMinor, 1_900, 'h', $productId, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: $unitPriceMinor === 10_000 ? 25_000 : null,
            controlVatMinor: $unitPriceMinor === 10_000 ? 4_750 : null,
            controlGrossMinor: $controlGrossMinor,
            partnerId: $partnerId,
            projectId: $projectId,
        );
    }

    private function partner(int $ownerId): int
    {
        return (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $ownerId,
            'name' => 'Recurring partner',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function project(int $ownerId): int
    {
        return (int) DB::table('finance_projects')->insertGetId([
            'user_id' => $ownerId,
            'name' => 'Recurring project',
            'kind' => 'business',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function product(int $ownerId): int
    {
        return (int) DB::table('finance_products')->insertGetId([
            'user_id' => $ownerId,
            'kind' => 'service',
            'name' => 'Recurring product',
            'price_net' => 100,
            'active' => true,
            'track_stock' => false,
            'stock_qty' => '0.0000',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped('Set FINANCE_TEST_PGSQL_URL to run the PostgreSQL recurring concurrency path.');
        }
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is required for the PostgreSQL recurring concurrency path.');
        }

        $connectionName = 'pgsql_task12_'.bin2hex(random_bytes(4));
        $schema = 'finance_invoice_task12_'.bin2hex(random_bytes(8));
        $base = config('database.connections.pgsql');
        $base = is_array($base) ? $base : [];
        config([
            "database.connections.{$connectionName}" => array_merge(
                $base,
                ['driver' => 'pgsql', 'url' => $postgresUrl, 'search_path' => $schema],
            ),
        ]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $defaultConnection = DB::getDefaultConnection();
        $schemaCreated = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $schemaCreated = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($connectionName);
            Schema::clearResolvedInstance('db.schema');
            Schema::create('users', static function (Blueprint $table): void {
                $table->id();
            });
            foreach ([
                '2026_08_28_100000_create_finance_document_core.php',
                '2026_08_28_110000_create_finance_invoices.php',
                '2026_08_28_110200_create_finance_recurring_invoices.php',
            ] as $migrationFile) {
                $migration = require database_path('migrations/'.$migrationFile);
                if (! is_object($migration) || ! is_callable([$migration, 'up'])) {
                    throw new \LogicException("Finance migration {$migrationFile} is unavailable.");
                }
                $migration->up();
            }
            DB::table('users')->insert(['id' => 1]);

            $test($postgresUrl, $schema);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');
            try {
                if ($schemaCreated) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($connectionName);
            }
        }
    }

    private function startPostgresVersionWorker(
        string $postgresUrl,
        string $schema,
        string $worker,
        int $templateId,
        string $effectiveFrom,
    ): Process {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $worker = getenv('FINANCE_TEST_RECURRING_WORKER');
            $templateId = filter_var(getenv('FINANCE_TEST_RECURRING_TEMPLATE_ID'), FILTER_VALIDATE_INT);
            $effectiveFrom = getenv('FINANCE_TEST_RECURRING_EFFECTIVE_FROM');
            if (! is_string($url) || ! is_string($schema) || ! is_string($worker)
                || ! is_int($templateId) || $templateId < 1
                || ! is_string($effectiveFrom)
                || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $effectiveFrom) !== 1
                || preg_match('/\Afinance_invoice_task12_[0-9a-f]{16}\z/D', $schema) !== 1) {
                fwrite(STDERR, 'invalid-postgres-recurring-worker-configuration');
                exit(90);
            }

            $base = config('database.connections.pgsql');
            $base = is_array($base) ? $base : [];
            foreach (['pgsql_task12_worker', 'pgsql_task12_barrier'] as $connectionName) {
                config([
                    "database.connections.{$connectionName}" => array_merge(
                        $base,
                        ['driver' => 'pgsql', 'url' => $url, 'search_path' => $schema],
                    ),
                ]);
                \Illuminate\Support\Facades\DB::purge($connectionName);
                \Illuminate\Support\Facades\DB::connection($connectionName)
                    ->statement('SET search_path TO "'.$schema.'"');
            }
            \Illuminate\Support\Facades\DB::setDefaultConnection('pgsql_task12_worker');
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
            \Illuminate\Support\Facades\DB::statement("SET lock_timeout TO '10s'");
            \Illuminate\Support\Facades\DB::statement("SET statement_timeout TO '20s'");

            $barrier = \Illuminate\Support\Facades\DB::connection('pgsql_task12_barrier');
            $barrier->table('finance_task12_version_barrier')->insert(['worker' => $worker]);
            $deadline = microtime(true) + 10.0;
            while ((int) $barrier->table('finance_task12_version_barrier')->count() < 2) {
                if (microtime(true) >= $deadline) {
                    fwrite(STDERR, 'postgres-recurring-barrier-timeout');
                    exit(91);
                }
                usleep(20_000);
            }

            $owner = new \App\Models\User;
            $owner->forceFill(['id' => 1]);
            \Illuminate\Support\Facades\Auth::setUser($owner);
            $clock = new \App\Modules\Finance\Infrastructure\Time\SystemClock;
            $idempotency = new \App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore($clock);
            $repository = new \App\Modules\Finance\Infrastructure\Persistence\EloquentRecurringInvoiceRepository(
                $idempotency,
                $clock,
            );
            $draft = new \App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData(
                new \DateTimeImmutable('2026-01-31'),
                new \DateTimeImmutable('2026-02-14'),
                'EUR',
                ['name' => 'ACME'],
                [new \App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData(
                    'Work', '1.0000', 10000, 1900, 'h', null, 'service',
                )],
                \App\Modules\Finance\Domain\Shared\Discount::none('EUR'),
            );

            try {
                $repository->addTemplateVersion(
                    new \App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId($templateId),
                    new \App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData(
                        new \DateTimeImmutable($effectiveFrom),
                        $draft,
                    ),
                    0,
                    new \App\Modules\Finance\Application\DTOs\IdempotencyKey('recurring-pg-'.$worker),
                );
                echo json_encode(['result' => 'ok'], JSON_THROW_ON_ERROR);
            } catch (\App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionConflict) {
                echo json_encode(['result' => 'conflict'], JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                fwrite(STDERR, $exception::class.':'.$exception->getMessage());
                exit(92);
            }
            PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script],
            base_path(),
            [
                'FINANCE_TEST_PGSQL_URL' => $postgresUrl,
                'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
                'FINANCE_TEST_RECURRING_WORKER' => $worker,
                'FINANCE_TEST_RECURRING_TEMPLATE_ID' => (string) $templateId,
                'FINANCE_TEST_RECURRING_EFFECTIVE_FROM' => $effectiveFrom,
            ],
            null,
            25,
        );
        $process->start();

        return $process;
    }
}
