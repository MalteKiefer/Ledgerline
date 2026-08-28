<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\CreateProjectFromQuote;
use App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectFromQuoteTarget;
use App\Modules\Finance\Infrastructure\Integrations\Quotes\FinanceQuoteProjectTarget;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class QuoteProjectTargetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(ProjectFromQuoteTarget::class, FinanceQuoteProjectTarget::class);
    }

    public function test_immutable_quote_snapshot_creates_one_atomic_project_and_service_tasks(): void
    {
        $owner = User::factory()->create();
        [$source,$seriesId] = $this->source($owner);
        $first = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'key-a');
        $replay = app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source, 'key-b');
        $this->assertSame($first->projectId->uuid, $replay->projectId->uuid);
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

    public function test_target_rejects_owner_hash_revision_and_float_integrity_without_writes(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        [$source] = $this->source($owner);
        foreach ([
            fn () => app(CreateProjectFromQuote::class)->handle((int) $foreign->id, $source, 'foreign'),
            fn () => app(CreateProjectFromQuote::class)->handle((int) $owner->id, $source->withSnapshotSha256(str_repeat('0', 64)), 'hash'),
            fn () => new ProjectQuoteSource($source->seriesUuid, $source->revisionId, $source->snapshotSha256, $source->number, $source->label, $source->title, $source->partnerReference, $source->issuedOn, $source->validUntil, $source->currency, $source->netMinor, $source->vatMinor, $source->grossMinor, [['kind' => 'service', 'description' => 'Bad', 'quantity' => 1.5, 'unit_price_minor' => 100]]),
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
        $lines = [['kind' => 'service', 'description' => "Discovery\nWorkshop and analysis", 'quantity' => '1.5000', 'unit_price_minor' => 50000, 'product_reference' => null], ['kind' => 'hardware', 'description' => 'Server', 'quantity' => '1.0000', 'unit_price_minor' => 50000, 'product_reference' => null]];
        $snapshot = ['currency' => 'EUR', 'lines' => $lines, 'title' => 'Website Relaunch'];
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId(['user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 1, 'previous_revision_id' => null, 'status' => 'published', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'net_minor' => 125000, 'vat_minor' => 23750, 'gross_minor' => 148750, 'currency' => 'EUR', 'change_reason' => null, 'pdf_path' => null, 'pdf_sha256' => null, 'published_at' => $now, 'created_by' => $owner->id, 'created_at' => $now]);

        return [new ProjectQuoteSource($seriesUuid, $revisionId, $hash, 'Q-2026-1', 'Quote Q-2026-1', 'Website Relaunch', null, new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2020-01-01'), 'EUR', 125000, 23750, 148750, $lines), $seriesId];
    }
}
