<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\CreateProjectFromQuote;
use App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectFromQuoteTarget;
use App\Modules\Finance\Infrastructure\Integrations\Quotes\FinanceQuoteProjectTarget;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
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
        try {
            app(CreateProjectFromQuote::class)->handle((int) $owner->id, new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $source->snapshotSha256, 'FORGED', $source->label, $source->snapshot), 'key-a');
            $this->fail('Changed input reused an idempotency key.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
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
        foreach ([new ProjectQuoteSource((string) Str::uuid(), $source->revisionId, $source->snapshotSha256, $source->number, $source->label, $source->snapshot), new ProjectQuoteSource($source->seriesUuid, $source->revisionId + 999, $source->snapshotSha256, $source->number, $source->label, $source->snapshot)] as $invalid) {
            try {
                app(CreateProjectFromQuote::class)->handle((int) $owner->id, $invalid, (string) Str::uuid());
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

    /** @return array{ProjectQuoteSource,int} */
    private function source(User $owner): array
    {
        $seriesUuid = (string) Str::uuid();
        $now = '2026-08-28 10:00:00';
        $seriesId = (int) DB::table('finance_document_series')->insertGetId(['user_id' => $owner->id, 'uuid' => $seriesUuid, 'document_type' => 'quote', 'status' => 'declined', 'source_type' => null, 'source_id' => null, 'created_by' => $owner->id, 'created_at' => $now, 'updated_at' => $now]);
        $lines = [
            ['description' => "Discovery\nWorkshop and analysis", 'quantity_scaled' => 15000, 'unit_price_minor' => 50000, 'currency' => 'EUR', 'tax_rate_basis_points' => 1900, 'quantity' => '1.5000', 'unit' => 'hour', 'unit_price' => '500.00', 'tax_rate' => '19.00', 'kind' => 'service', 'product_id' => null],
            ['description' => 'Server', 'quantity_scaled' => 10000, 'unit_price_minor' => 50000, 'currency' => 'EUR', 'tax_rate_basis_points' => 1900, 'quantity' => '1.0000', 'unit' => 'piece', 'unit_price' => '500.00', 'tax_rate' => '19.00', 'kind' => 'hardware', 'product_id' => null],
        ];
        $snapshot = ['currency' => 'EUR', 'issue_date' => '2026-08-01', 'lines' => $lines, 'partner_id' => null, 'title' => 'Website Relaunch', 'totals' => ['net_minor' => 125000, 'vat_minor' => 23750, 'gross_minor' => 148750, 'discount_minor' => 0, 'currency' => 'EUR', 'tax_breakdowns' => []], 'valid_until' => '2020-01-01'];
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId(['user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 1, 'previous_revision_id' => null, 'status' => 'published', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'net_minor' => 125000, 'vat_minor' => 23750, 'gross_minor' => 148750, 'currency' => 'EUR', 'change_reason' => null, 'pdf_path' => null, 'pdf_sha256' => null, 'published_at' => $now, 'created_by' => $owner->id, 'created_at' => $now]);
        DB::table('finance_quote_series')->insert(['document_series_id' => $seriesId, 'user_id' => $owner->id, 'document_type' => 'quote', 'partner_id' => null, 'current_revision_id' => $revisionId, 'number' => 'Q-2026-1', 'sequence_year' => 2026, 'sequence_number' => 1, 'version' => 0, 'published_at' => $now, 'accepted_at' => $now, 'declined_at' => null, 'converted_at' => null, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now]);

        return [new ProjectQuoteSource($seriesUuid, $revisionId, $hash, 'Q-2026-1', 'Quote Q-2026-1', $snapshot), $seriesId];
    }
}
