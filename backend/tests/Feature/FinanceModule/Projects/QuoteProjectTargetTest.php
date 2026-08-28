<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\CreateProjectFromQuote;
use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectFromQuoteTarget;
use App\Modules\Finance\Application\Services\CanonicalDocumentSnapshot;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Integrations\Quotes\FinanceQuoteProjectTarget;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class QuoteProjectTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_has_a_production_binding(): void
    {
        $this->assertInstanceOf(FinanceQuoteProjectTarget::class, app(ProjectFromQuoteTarget::class));
    }

    public function test_immutable_quote_snapshot_creates_one_atomic_project_and_service_tasks(): void
    {
        $owner = User::factory()->create();
        [$source,$seriesId] = $this->source($owner);
        $first = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'key-a');
        $changedSnapshot = [...$source->snapshot, 'title' => 'Changed title'];
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, $this->sourceFromSnapshot($source, $changedSnapshot), 'key-a');
            $this->fail('Changed input reused an idempotency key.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }
        try {
            new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $source->snapshotSha256, $source->number, $source->label, $changedSnapshot);
            $this->fail('A changed snapshot reused its old digest.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('project_quote_snapshot_hash_mismatch', $exception->getMessage());
        }
        DB::table('finance_project_document_links')->update(['detached_by' => $owner->id, 'detached_at' => '2026-08-28 11:00:00']);
        DB::table('finance_project_operations')->where('idempotency_key', 'key-a')->update(['state' => 'running', 'result' => null, 'completed_at' => null]);
        $recovered = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'key-a');
        $replay = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'key-a');
        $otherKey = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'key-b');
        $this->assertSame($first->projectId->uuid, $replay->projectId->uuid);
        $this->assertSame($first->projectId->uuid, $recovered->projectId->uuid);
        $this->assertSame($first->projectId->uuid, $otherKey->projectId->uuid);
        $project = DB::table('finance_project_records')->where('uuid', $first->projectId->uuid)->first();
        $this->assertSame('Website Relaunch', $project->name);
        $this->assertSame(125000, $project->budget_minor);
        $this->assertSame('EUR', $project->currency);
        $tasks = DB::table('finance_project_work_items')->where('project_id', $project->id)->orderBy('sort')->get();
        $this->assertCount(1, $tasks);
        $this->assertSame('Discovery', $tasks[0]->title);
        $this->assertSame('Workshop and analysis', $tasks[0]->description);
        $this->assertSame(15000, $tasks[0]->estimate_quantity_scaled);
        $this->assertSame($source->revisionId, $tasks[0]->source_revision_id);
        $this->assertSame(0, $tasks[0]->source_line_index);
        $link = DB::table('finance_project_document_links')->where('project_id', $project->id)->first();
        $this->assertSame('source_quote', $link->role);
        $this->assertSame($seriesId, $link->document_series_id);
        $this->assertSame($source->revisionId, $link->pinned_revision_id);
        $metadata = json_decode((string) $link->metadata_snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Q-2026-1', $metadata['label']);
        $this->assertSame(['project.created_from_quote', 'project.document_attached', 'work_item.created'], DB::table('finance_project_activities')->orderBy('id')->pluck('type')->all());
        $this->assertSame(1, DB::table('finance_project_records')->count());
    }

    public function test_revision_series_metadata_and_scale_integrity_are_owner_safe(): void
    {
        $owner = User::factory()->create();
        [$source] = $this->source($owner);
        $invalidScale = [...$source->snapshot, 'lines' => [[...$source->lines[0], 'quantity_scaled' => 14999], $source->lines[1]]];
        try {
            new ProjectQuoteSource($source->seriesUuid, $source->revisionId, hash('sha256', json_encode($invalidScale, JSON_THROW_ON_ERROR)), $source->number, $source->label, $invalidScale);
            $this->fail('Invalid scale accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        foreach ([
            fn () => new ProjectQuoteSource((string) Str::uuid(), $source->revisionId, $source->snapshotSha256, $source->number, $source->label, $source->snapshot),
            fn () => app(CreateProjectFromQuote::class)->handle((int) $owner->id, new ProjectQuoteSource($source->seriesUuid, $source->revisionId + 999, $source->snapshotSha256, $source->number, $source->label, $source->snapshot), (string) Str::uuid()),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Unknown source accepted.');
            } catch (ModelNotFoundException|InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $forged = [...$source->snapshot, 'title' => 'Forged'];
        $forgedSource = new ProjectQuoteSource($source->seriesUuid, $source->revisionId, hash('sha256', json_encode($forged, JSON_THROW_ON_ERROR)), $source->number, $source->label, $forged);
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, $forgedSource, 'forged');
            $this->fail('Forged metadata accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $source->snapshotSha256, 'FORGED', 'FORGED', $source->snapshot), 'forged-series');
            $this->fail('Forged series metadata accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, DB::table('finance_project_records')->count());
    }

    public function test_source_rejects_noncanonical_quote_schema_and_inconsistent_exact_values_before_reservation(): void
    {
        $owner = User::factory()->create();
        [$source] = $this->source($owner);
        $reordered = array_reverse($source->snapshot, true);
        $normalized = new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $source->snapshotSha256, $source->number, $source->label, $reordered);
        $this->assertSame($source->snapshot, $normalized->snapshot);
        $invalidSnapshots = [
            'schema version' => [...$source->snapshot, 'schema_version' => 2],
            'document type' => [...$source->snapshot, 'document_type' => 'invoice'],
            'series UUID' => [...$source->snapshot, 'series_uuid' => (string) Str::uuid()],
            'revision number' => [...$source->snapshot, 'revision_number' => 0],
            'revision label' => [...$source->snapshot, 'revision_label' => ''],
            'issue date format' => [...$source->snapshot, 'issue_date' => '2026-8-1'],
            'date order' => [...$source->snapshot, 'issue_date' => '2026-09-01', 'valid_until' => '2026-08-31'],
            'totals currency' => [...$source->snapshot, 'totals' => [...$source->snapshot['totals'], 'currency' => 'USD']],
            'line kind' => [...$source->snapshot, 'lines' => [[...$source->lines[0], 'kind' => 'fee'], $source->lines[1]]],
            'product ID' => [...$source->snapshot, 'lines' => [[...$source->lines[0], 'product_id' => 0], $source->lines[1]]],
            'quantity exactness' => [...$source->snapshot, 'lines' => [[...$source->lines[0], 'quantity' => '1.4999'], $source->lines[1]]],
            'unit price exactness' => [...$source->snapshot, 'lines' => [[...$source->lines[0], 'unit_price' => '499.99'], $source->lines[1]]],
            'tax exactness' => [...$source->snapshot, 'lines' => [[...$source->lines[0], 'tax_rate' => '18.99'], $source->lines[1]]],
            'document totals' => [...$source->snapshot, 'totals' => [...$source->snapshot['totals'], 'gross_minor' => 148751]],
            'tax breakdowns' => [...$source->snapshot, 'totals' => [...$source->snapshot['totals'], 'tax_breakdowns' => []]],
        ];

        foreach ($invalidSnapshots as $case => $snapshot) {
            try {
                $this->sourceFromSnapshot($source, $snapshot);
                $this->fail("Invalid canonical quote schema accepted: {$case}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('finance_project_operations')->count());
        $this->assertSame(0, DB::table('finance_project_records')->count());
    }

    public function test_revision_number_and_revision_label_are_read_from_the_canonical_snapshot(): void
    {
        $owner = User::factory()->create();
        [$source,$seriesId] = $this->source($owner);
        $snapshot = [...$source->snapshot, 'revision_number' => 2, 'revision_label' => 'Q-2026-1-R2'];
        $canonical = (new CanonicalDocumentSnapshot)->canonicalize($snapshot);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 2,
            'previous_revision_id' => $source->revisionId,
            'status' => 'published',
            'snapshot' => json_encode($canonical, JSON_THROW_ON_ERROR),
            'net_minor' => 125000,
            'vat_minor' => 23750,
            'gross_minor' => 148750,
            'currency' => 'EUR',
            'change_reason' => 'Scope revised',
            'pdf_path' => null,
            'pdf_sha256' => null,
            'published_at' => '2026-08-28 10:30:00',
            'created_by' => $owner->id,
            'created_at' => '2026-08-28 10:30:00',
        ]);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update(['current_revision_id' => $revisionId]);
        $revisionSource = new ProjectQuoteSource($source->seriesUuid, $revisionId, hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)), 'Q-2026-1', 'Q-2026-1-R2', $canonical);

        $target = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $revisionSource, 'revision-two');
        $link = DB::table('finance_project_document_links')
            ->join('finance_project_records', 'finance_project_records.id', '=', 'finance_project_document_links.project_id')
            ->where('finance_project_records.uuid', $target->projectId->uuid)
            ->select('metadata_snapshot')
            ->sole();
        $metadata = json_decode((string) $link->metadata_snapshot, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Q-2026-1-R2', $metadata['label']);
    }

    public function test_foreign_product_and_midwrite_failure_roll_back_every_project_write(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $partner = new FinancePartner;
        $partner->forceFill(['user_id' => $foreign->id, 'name' => 'Foreign', 'kind' => 'customer', 'version' => 0])->save();
        $product = new FinanceProduct;
        $product->forceFill(['user_id' => $foreign->id, 'name' => 'Foreign', 'kind' => 'service', 'unit' => 'hour', 'price_net' => '10.00'])->save();
        [$source] = $this->source($owner);
        $partnerSnapshot = $source->snapshot;
        $partnerSnapshot['partner_id'] = (int) $partner->id;
        $partnerHash = hash('sha256', json_encode($partnerSnapshot, JSON_THROW_ON_ERROR));
        DB::table('finance_document_revisions')->where('id', $source->revisionId)->update(['snapshot' => json_encode($partnerSnapshot, JSON_THROW_ON_ERROR)]);
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $partnerHash, $source->number, $source->label, $partnerSnapshot), 'foreign-partner');
            $this->fail('Foreign partner accepted.');
        } catch (InvalidArgumentException|ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $snapshot = $source->snapshot;
        $snapshot['lines'][0]['product_id'] = (int) $product->id;
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        DB::table('finance_document_revisions')->where('id', $source->revisionId)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $foreignSource = new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $hash, $source->number, $source->label, $snapshot);
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, $foreignSource, 'foreign-product');
            $this->fail('Foreign product accepted.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        DB::table('finance_document_revisions')->where('id', $source->revisionId)->update(['snapshot' => json_encode($source->snapshot, JSON_THROW_ON_ERROR)]);
        $thrown = false;
        DB::listen(function (QueryExecuted $query) use (&$thrown): void {
            if (! $thrown && str_contains($query->sql, 'insert into "finance_project_work_items"')) {
                $thrown = true;
                throw new RuntimeException('midwrite');
            }
        });
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'midwrite');
            $this->fail('Injected failure swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('midwrite', $exception->getMessage());
        }
        $this->assertTrue($thrown);
        $this->assertSame(0, DB::table('finance_project_records')->count());
        $this->assertSame(0, DB::table('finance_project_document_links')->count());
    }

    public function test_overlapping_different_keys_serialize_on_the_series_and_return_one_project(): void
    {
        $owner = User::factory()->create();
        [$source] = $this->source($owner);
        $interleaved = false;
        $inner = null;
        DB::listen(function (QueryExecuted $query) use (&$interleaved, &$inner, $owner, $source): void {
            if (! $interleaved && str_contains($query->sql, 'from "finance_document_series"')) {
                $interleaved = true;
                $inner = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'inner-key');
            }
        });
        $outer = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'outer-key');
        $this->assertTrue($interleaved);
        $this->assertNotNull($inner);
        $this->assertSame($inner->projectId->uuid, $outer->projectId->uuid);
        $this->assertSame(1, DB::table('finance_project_records')->count());
    }

    public function test_target_rejects_owner_hash_revision_and_float_integrity_without_writes(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        [$source] = $this->source($owner);
        foreach ([
            fn () => app(CreateProjectFromQuote::class)->handle((int) $foreign->id, $source, 'foreign'),
            fn () => app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source->withSnapshotSha256(str_repeat('0', 64)), 'hash'),
            fn () => new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $source->snapshotSha256, $source->number, $source->label, [...$source->snapshot, 'lines' => [[...$source->lines[0], 'quantity' => 1.5]]]),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Invalid quote source accepted.');
            } catch (InvalidArgumentException|ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('finance_project_records')->count());
    }

    public function test_postgresql_same_and_different_idempotency_keys_serialize_to_one_project_when_configured(): void
    {
        $this->withIsolatedPostgresQuoteProjectSchema(function (string $postgresUrl, string $schema): void {
            [$sameKeySource] = $this->storedSource(1, 'Q-PG-SAME');
            $this->assertConcurrentPostgresTarget($postgresUrl, $schema, $sameKeySource, 'same-key', 'same-key', 'finance_project_operations');

            [$differentKeySource] = $this->storedSource(1, 'Q-PG-DIFFERENT');
            $this->assertConcurrentPostgresTarget($postgresUrl, $schema, $differentKeySource, 'parent-key', 'worker-key', 'finance_document_series');

            $this->assertSame(2, DB::table('finance_project_records')->count());
            $this->assertSame(2, DB::table('finance_project_document_links')->where('role', 'source_quote')->count());
        });
    }

    /** @return array{ProjectQuoteSource,int} */
    private function source(User $owner): array
    {
        return $this->storedSource((int) $owner->id, 'Q-2026-1');
    }

    /** @return array{ProjectQuoteSource,int} */
    private function storedSource(int $ownerId, string $number): array
    {
        $seriesUuid = (string) Str::uuid();
        $now = '2026-08-28 10:00:00';
        $seriesId = (int) DB::table('finance_document_series')->insertGetId(['user_id' => $ownerId, 'uuid' => $seriesUuid, 'document_type' => 'quote', 'status' => 'declined', 'source_type' => null, 'source_id' => null, 'created_by' => $ownerId, 'created_at' => $now, 'updated_at' => $now]);
        $snapshot = $this->canonicalQuoteSnapshot($seriesUuid, $number);
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId(['user_id' => $ownerId, 'document_series_id' => $seriesId, 'revision_number' => 1, 'previous_revision_id' => null, 'status' => 'published', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'net_minor' => 125000, 'vat_minor' => 23750, 'gross_minor' => 148750, 'currency' => 'EUR', 'change_reason' => null, 'pdf_path' => null, 'pdf_sha256' => null, 'published_at' => $now, 'created_by' => $ownerId, 'created_at' => $now]);
        DB::table('finance_quote_series')->insert(['document_series_id' => $seriesId, 'user_id' => $ownerId, 'document_type' => 'quote', 'partner_id' => null, 'current_revision_id' => $revisionId, 'number' => $number, 'sequence_year' => 2026, 'sequence_number' => 1, 'version' => 0, 'published_at' => $now, 'accepted_at' => $now, 'declined_at' => null, 'converted_at' => null, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now]);

        return [new ProjectQuoteSource($seriesUuid, $revisionId, $hash, $number, $number, $snapshot), $seriesId];
    }

    /** @param array<string, mixed> $snapshot */
    private function sourceFromSnapshot(ProjectQuoteSource $source, array $snapshot): ProjectQuoteSource
    {
        $number = $snapshot['document_number'] ?? $source->number;
        $label = $snapshot['revision_label'] ?? $source->label;

        return new ProjectQuoteSource(
            $source->seriesUuid,
            $source->revisionId,
            hash('sha256', json_encode((new CanonicalDocumentSnapshot)->canonicalize($snapshot), JSON_THROW_ON_ERROR)),
            is_string($number) ? $number : $source->number,
            is_string($label) ? $label : $source->label,
            $snapshot,
        );
    }

    /** @return array<string, mixed> */
    private function canonicalQuoteSnapshot(string $seriesUuid, string $number): array
    {
        $lines = [
            new DocumentLine("Discovery\nWorkshop and analysis", DecimalQuantity::fromString('1.5000'), Money::fromDecimal('500.00', 'EUR'), 1900),
            new DocumentLine('Server', DecimalQuantity::fromString('1.0000'), Money::fromDecimal('500.00', 'EUR'), 1900),
        ];
        $discount = Discount::none('EUR');
        $totals = (new DocumentCalculator)->calculate($lines, $discount);

        return (new CanonicalDocumentSnapshot)->build(new CreateRevisionData(
            seriesUuid: $seriesUuid,
            snapshot: [
                'schema_version' => 1,
                'document_type' => 'quote',
                'series_uuid' => $seriesUuid,
                'document_number' => $number,
                'revision_number' => 1,
                'revision_label' => $number,
                'title' => 'Website Relaunch',
                'customer' => ['name' => 'Expired Customer'],
                'partner_id' => null,
                'issue_date' => '2020-01-01',
                'valid_until' => '2020-01-31',
                'currency' => 'EUR',
                'lines' => [
                    ['quantity' => '1.5000', 'unit' => 'hour', 'unit_price' => '500.00', 'tax_rate' => '19.00', 'kind' => 'service', 'product_id' => null],
                    ['quantity' => '1.0000', 'unit' => 'piece', 'unit_price' => '500.00', 'tax_rate' => '19.00', 'kind' => 'hardware', 'product_id' => null],
                ],
                'discount' => ['type' => 'none', 'value' => null, 'currency' => 'EUR'],
                'totals' => [],
                'intro_text' => null,
                'outro_text' => null,
                'customer_note' => null,
            ],
            lines: $lines,
            discount: $discount,
        ), $totals);
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresQuoteProjectSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped('Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run the quote-to-project PostgreSQL concurrency test.');
        }

        $defaultConnection = DB::getDefaultConnection();
        $connectionName = 'pgsql_project_quote_concurrency';
        $schema = 'finance_project_task7_'.bin2hex(random_bytes(8));
        $postgresConfig = config('database.connections.pgsql');
        if (! is_array($postgresConfig)) {
            throw new \LogicException('PostgreSQL connection configuration is unavailable.');
        }
        config(["database.connections.{$connectionName}" => array_merge($postgresConfig, ['url' => $postgresUrl, 'search_path' => 'public'])]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
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
                require database_path('migrations/2026_08_28_100000_create_finance_document_core.php'),
                require database_path('migrations/2027_03_04_100000_create_finance_project_workflow.php'),
            ] as $migration) {
                if (! is_object($migration) || ! is_callable([$migration, 'up'])) {
                    throw new \LogicException('Finance project migration is unavailable.');
                }
                call_user_func([$migration, 'up']);
            }
            Schema::create('finance_quote_series', static function (Blueprint $table): void {
                $table->unsignedBigInteger('document_series_id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->string('document_type', 16);
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->unsignedBigInteger('current_revision_id')->nullable();
                $table->string('number', 64)->nullable();
                $table->unsignedSmallInteger('sequence_year')->nullable();
                $table->unsignedInteger('sequence_number')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
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

    private function assertConcurrentPostgresTarget(string $postgresUrl, string $schema, ProjectQuoteSource $source, string $parentKey, string $workerKey, string $expectedLockTable): void
    {
        $process = null;
        DB::beginTransaction();
        try {
            $parent = app(CreateProjectFromQuote::class)->handle(1, $source, $parentKey);
            $process = $this->startPostgresQuoteProjectWorker($postgresUrl, $schema, $source, $workerKey);
            $backendPid = $this->waitForPostgresQuoteProjectWorker($process);
            $lockQuery = $this->waitForPostgresQuoteProjectLock($process, $backendPid);
            $this->assertStringContainsString($expectedLockTable, strtolower($lockQuery));
            $this->assertTrue($process->isRunning(), 'The competing conversion did not wait on the expected database lock.');

            DB::commit();
            $process->wait();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process instanceof Process && $process->isRunning()) {
                $process->stop(1.0);
            }
        }

        $this->assertInstanceOf(Process::class, $process);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $lines = array_values(array_filter(preg_split('/\R/', trim($process->getOutput())) ?: []));
        $payload = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($parent->projectId->uuid, $payload['project_uuid'] ?? null);
        $this->assertSame(1, DB::table('finance_project_document_links')->where('pinned_revision_id', $source->revisionId)->count());
    }

    private function startPostgresQuoteProjectWorker(string $postgresUrl, string $schema, ProjectQuoteSource $source, string $key): Process
    {
        $sourcePayload = base64_encode(json_encode([
            'series_uuid' => $source->seriesUuid,
            'revision_id' => $source->revisionId,
            'snapshot_sha256' => $source->snapshotSha256,
            'number' => $source->number,
            'label' => $source->label,
            'snapshot' => $source->snapshot,
        ], JSON_THROW_ON_ERROR));
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $encodedSource = getenv('FINANCE_TEST_PROJECT_QUOTE_SOURCE');
            $key = getenv('FINANCE_TEST_PROJECT_QUOTE_KEY');
            if (! is_string($url) || ! is_string($schema) || ! is_string($encodedSource) || ! is_string($key)
                || preg_match('/\Afinance_project_task7_[0-9a-f]{16}\z/D', $schema) !== 1) {
                fwrite(STDERR, 'invalid-postgres-worker-configuration');
                exit(90);
            }

            $connectionName = 'pgsql_project_quote_worker';
            $base = config('database.connections.pgsql');
            config(["database.connections.{$connectionName}" => array_merge(
                is_array($base) ? $base : [],
                ['driver' => 'pgsql', 'url' => $url, 'search_path' => $schema],
            )]);
            \Illuminate\Support\Facades\DB::purge($connectionName);
            \Illuminate\Support\Facades\DB::setDefaultConnection($connectionName);
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
            \Illuminate\Support\Facades\DB::statement('SET search_path TO "'.$schema.'"');
            \Illuminate\Support\Facades\DB::statement("SET lock_timeout TO '8s'");
            \Illuminate\Support\Facades\DB::statement("SET statement_timeout TO '15s'");
            $payload = json_decode(base64_decode($encodedSource, true), true, flags: JSON_THROW_ON_ERROR);
            $source = new \App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource(
                $payload['series_uuid'],
                $payload['revision_id'],
                $payload['snapshot_sha256'],
                $payload['number'],
                $payload['label'],
                $payload['snapshot'],
            );
            echo 'ready='.\Illuminate\Support\Facades\DB::scalar('SELECT pg_backend_pid()')."\n";
            flush();
            try {
                $target = $app->make(\App\Modules\Finance\Application\Commands\Projects\CreateProjectFromQuote::class)
                    ->handle(1, $source, $key);
                echo json_encode(['project_uuid' => $target->projectId->uuid], JSON_THROW_ON_ERROR)."\n";
                exit(0);
            } catch (Throwable $exception) {
                fwrite(STDERR, $exception::class.':'.$exception->getMessage());
                exit(91);
            }
            PHP;

        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'FINANCE_TEST_PGSQL_URL' => $postgresUrl,
            'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
            'FINANCE_TEST_PROJECT_QUOTE_SOURCE' => $sourcePayload,
            'FINANCE_TEST_PROJECT_QUOTE_KEY' => $key,
        ], null, 20);
        $process->start();

        return $process;
    }

    private function waitForPostgresQuoteProjectWorker(Process $process): int
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            if (preg_match('/ready=(\d+)/', $process->getOutput(), $matches) === 1) {
                return (int) $matches[1];
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(20_000);
        }

        $this->fail('PostgreSQL quote-project worker did not become ready: '.$process->getErrorOutput().$process->getOutput());
    }

    private function waitForPostgresQuoteProjectLock(Process $process, int $backendPid): string
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $activity = DB::table('pg_catalog.pg_stat_activity')->where('pid', $backendPid)->first(['wait_event_type', 'query']);
            if (is_object($activity) && $activity->wait_event_type === 'Lock' && is_string($activity->query)) {
                $this->addToAssertionCount(1);

                return $activity->query;
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(20_000);
        }

        $this->fail('PostgreSQL quote-project worker did not wait on a lock: '.$process->getErrorOutput().$process->getOutput());
    }
}
