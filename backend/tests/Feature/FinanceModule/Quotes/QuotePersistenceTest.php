<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteReferenceResolver;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Application\Queries\Quotes\ListQuoteRevisions;
use App\Modules\Finance\Application\Queries\Quotes\ListQuotes;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteConversionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteDraftRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteOperationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteSeriesRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class QuotePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_do_not_mass_assign_server_controlled_identity_and_history_fields(): void
    {
        $guardedFields = [
            [new QuoteSeriesRecord, [
                'document_series_id', 'user_id', 'document_type', 'current_revision_id',
                'number', 'sequence_year', 'sequence_number', 'version', 'published_at',
                'accepted_at', 'declined_at', 'converted_at',
            ]],
            [new QuoteDraftRecord, [
                'document_series_id', 'user_id', 'based_on_revision_id', 'updated_by',
            ]],
            [new QuoteOperationRecord, [
                'user_id', 'document_series_id', 'request_sha256', 'state', 'result',
                'error_code', 'started_at', 'completed_at',
            ]],
            [new QuoteDeliveryRecord, [
                'user_id', 'document_series_id', 'document_revision_id', 'message_id',
                'state', 'attempts', 'sent_at', 'failed_at',
            ]],
            [new QuoteConversionRecord, [
                'user_id', 'document_series_id', 'source_revision_id', 'target_type',
                'target_reference', 'target_id', 'created_at',
            ]],
        ];

        foreach ($guardedFields as [$record, $fields]) {
            foreach ($fields as $field) {
                $this->assertFalse($record->isFillable($field), sprintf(
                    '%s must not allow mass assignment of %s.',
                    $record::class,
                    $field,
                ));
            }
        }
    }

    public function test_repository_creates_reads_and_compare_and_swaps_an_owner_scoped_draft(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);

        $created = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'First draft', 'valid_until' => '2026-10-31'],
            $this->totals(10_000, 1_900, 11_900),
        );

        $this->assertSame((int) $owner->id, $created->id->ownerId);
        $this->assertSame('draft', $created->status);
        $this->assertSame('draft', $created->effectiveStatus);
        $this->assertSame(0, $created->version);
        $this->assertSame('First draft', $created->draft['title']);
        $this->assertSame(11_900, $created->grossMinor);
        $this->assertEquals($created, $repository->get($created->id));

        $updated = $repository->updateDraft(
            $created->id,
            expectedVersion: 0,
            payload: ['title' => 'Updated draft', 'valid_until' => '2026-11-30'],
            totals: $this->totals(20_000, 3_800, 23_800),
        );

        $this->assertSame(1, $updated->version);
        $this->assertSame('Updated draft', $updated->draft['title']);
        $this->assertSame(23_800, $updated->grossMinor);

        $current = $repository->updateDraft(
            $created->id,
            expectedVersion: 0,
            payload: ['title' => 'Stale write'],
            totals: $this->totals(99, 19, 118),
        );

        $this->assertSame(1, $current->version);
        $this->assertSame('Updated draft', $current->draft['title']);
        $this->assertSame(23_800, $current->grossMinor);

        $this->expectException(ModelNotFoundException::class);
        $repository->get(new QuoteId((int) $otherOwner->id, $created->id->uuid));
    }

    public function test_soft_deleted_quote_is_absent_from_reads_revisions_writes_pages_and_operations(): void
    {
        $owner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $operations = $this->app->make(QuoteOperationRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Deleted quote'],
            $this->totals(10_000, 1_900, 11_900),
        );
        DB::table('finance_quote_series')
            ->where('user_id', $owner->id)
            ->where('document_series_id', $this->seriesId($quote))
            ->update(['deleted_at' => now()]);

        $this->assertModelNotFound(fn () => $repository->get($quote->id));
        $this->assertModelNotFound(fn () => $repository->revisions($quote->id));
        $this->assertModelNotFound(fn () => $repository->updateDraft(
            $quote->id,
            expectedVersion: 0,
            payload: ['title' => 'Resurrected'],
            totals: $this->totals(20_000, 3_800, 23_800),
        ));
        $this->assertModelNotFound(fn () => $operations->reserve(
            (int) $owner->id,
            'publish',
            'deleted-quote',
            hash('sha256', '{}'),
            $quote->id,
        ));
        $this->assertSame(
            0,
            $repository->page(['owner_id' => (int) $owner->id], 1, 20)->total,
        );
    }

    public function test_create_validates_and_persists_an_owned_partner_atomically(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $ownedPartnerId = $this->insertPartner((int) $owner->id, 'Owned partner');
        $foreignPartnerId = $this->insertPartner((int) $otherOwner->id, 'Foreign partner');
        $deletedPartnerId = $this->insertPartner((int) $owner->id, 'Deleted partner');
        DB::table('finance_partners')->where('id', $deletedPartnerId)->update(['deleted_at' => now()]);
        $repository = $this->app->make(QuoteRepository::class);

        $created = $repository->createDraft(
            (int) $owner->id,
            partnerId: $ownedPartnerId,
            payload: ['title' => 'Partner quote'],
            totals: $this->totals(10_000, 1_900, 11_900),
        );

        $this->assertSame($ownedPartnerId, $created->partnerId);
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());

        $this->assertModelNotFound(fn () => $repository->createDraft(
            (int) $owner->id,
            partnerId: $foreignPartnerId,
            payload: ['title' => 'Foreign partner quote'],
            totals: $this->totals(20_000, 3_800, 23_800),
        ));
        $this->assertModelNotFound(fn () => $repository->createDraft(
            (int) $owner->id,
            partnerId: $deletedPartnerId,
            payload: ['title' => 'Deleted partner quote'],
            totals: $this->totals(30_000, 5_700, 35_700),
        ));
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
    }

    public function test_partner_change_is_part_of_the_draft_compare_and_swap(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $firstPartnerId = $this->insertPartner((int) $owner->id, 'First partner');
        $secondPartnerId = $this->insertPartner((int) $owner->id, 'Second partner');
        $foreignPartnerId = $this->insertPartner((int) $otherOwner->id, 'Foreign partner');
        $repository = $this->app->make(QuoteRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            partnerId: $firstPartnerId,
            payload: ['title' => 'Original'],
            totals: $this->totals(10_000, 1_900, 11_900),
        );

        $updated = $repository->updateDraft(
            $quote->id,
            expectedVersion: 0,
            partnerId: $secondPartnerId,
            payload: ['title' => 'Updated'],
            totals: $this->totals(20_000, 3_800, 23_800),
        );
        $this->assertSame($secondPartnerId, $updated->partnerId);
        $this->assertSame(1, $updated->version);

        $stale = $repository->updateDraft(
            $quote->id,
            expectedVersion: 0,
            partnerId: $firstPartnerId,
            payload: ['title' => 'Stale'],
            totals: $this->totals(30_000, 5_700, 35_700),
        );
        $this->assertSame($secondPartnerId, $stale->partnerId);
        $this->assertSame('Updated', $stale->draft['title']);

        $this->assertModelNotFound(fn () => $repository->updateDraft(
            $quote->id,
            expectedVersion: 1,
            partnerId: $foreignPartnerId,
            payload: ['title' => 'Foreign'],
            totals: $this->totals(40_000, 7_600, 47_600),
        ));
        $unchanged = $repository->get($quote->id);
        $this->assertSame(1, $unchanged->version);
        $this->assertSame($secondPartnerId, $unchanged->partnerId);
        $this->assertSame('Updated', $unchanged->draft['title']);

        $cleared = $repository->updateDraft(
            $quote->id,
            expectedVersion: 1,
            partnerId: null,
            payload: ['title' => 'No partner'],
            totals: $this->totals(50_000, 9_500, 59_500),
        );
        $this->assertNull($cleared->partnerId);
        $this->assertSame(2, $cleared->version);
    }

    public function test_successful_draft_cas_updates_root_and_extension_at_the_same_clock_instant(): void
    {
        $owner = User::factory()->create();
        $this->app->instance(Clock::class, $this->clockAt('2029-04-05 06:07:08 UTC'));
        $repository = $this->app->make(QuoteRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Before timestamp update'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $seriesId = $this->seriesId($quote);
        $beforeRoot = DB::table('finance_document_series')->where('id', $seriesId)->value('updated_at');
        $beforeExtension = DB::table('finance_quote_series')
            ->where('document_series_id', $seriesId)
            ->value('updated_at');
        $this->assertSame('2029-04-05 06:07:08', $beforeRoot);
        $this->assertSame($beforeRoot, $beforeExtension);

        $this->app->instance(Clock::class, $this->clockAt('2030-05-06 07:08:09 UTC'));
        $updated = $this->app->make(QuoteRepository::class)->updateDraft(
            $quote->id,
            expectedVersion: 0,
            payload: ['title' => 'After timestamp update'],
            totals: $this->totals(20_000, 3_800, 23_800),
        );

        $rootUpdatedAt = DB::table('finance_document_series')->where('id', $seriesId)->value('updated_at');
        $extensionUpdatedAt = DB::table('finance_quote_series')
            ->where('document_series_id', $seriesId)
            ->value('updated_at');
        $this->assertNotSame($beforeRoot, $rootUpdatedAt);
        $this->assertSame('2030-05-06 07:08:09', $rootUpdatedAt);
        $this->assertSame($rootUpdatedAt, $extensionUpdatedAt);
        $this->assertSame('2030-05-06 07:08:09', $updated->updatedAt->format('Y-m-d H:i:s'));
    }

    public function test_revision_history_is_descending_and_cannot_resolve_a_foreign_owner_uuid(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Versioned'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $seriesId = (int) DB::table('finance_document_series')
            ->where('user_id', $owner->id)
            ->where('uuid', $quote->id->uuid)
            ->value('id');
        $firstId = $this->insertRevision((int) $owner->id, $seriesId, 1, null);
        $secondId = $this->insertRevision((int) $owner->id, $seriesId, 2, $firstId);

        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $secondId,
            'published_at' => '2026-08-28 10:00:00',
        ]);
        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => 'sent']);

        $revisions = $repository->revisions($quote->id);

        $this->assertSame([2, 1], array_column($revisions, 'revisionNumber'));
        $this->assertSame([$secondId, $firstId], array_column($revisions, 'id'));

        $this->expectException(ModelNotFoundException::class);
        $repository->revisions(new QuoteId((int) $otherOwner->id, $quote->id->uuid));
    }

    public function test_pages_apply_owner_query_status_expiry_and_published_date_filters(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $matching = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Needle migration'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $outsideDate = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Needle outside'],
            $this->totals(20_000, 3_800, 23_800),
        );
        $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Different title'],
            $this->totals(30_000, 5_700, 35_700),
        );
        $foreign = $repository->createDraft(
            (int) $otherOwner->id,
            ['title' => 'Needle migration'],
            $this->totals(40_000, 7_600, 47_600),
        );
        $this->publishQuote($matching, 'sent', '2026-08-20 12:00:00', '2000-01-01');
        $this->publishQuote($outsideDate, 'sent', '2026-07-31 12:00:00', '2000-01-01');
        $this->publishQuote($foreign, 'sent', '2026-08-20 12:00:00', '2000-01-01');

        $page = $repository->page([
            'owner_id' => (int) $owner->id,
            'q' => 'needle',
            'status' => 'sent',
            'effective_status' => 'expired',
            'published_from' => '2026-08-01',
            'published_to' => '2026-08-31',
        ], page: 1, perPage: 10);

        $this->assertSame(1, $page->total);
        $this->assertSame(1, $page->page);
        $this->assertSame(10, $page->perPage);
        $this->assertSame([$matching->id->uuid], array_map(
            static fn ($quote): string => $quote->id->uuid,
            $page->items,
        ));
        $this->assertSame('expired', $page->items[0]->effectiveStatus);
    }

    public function test_pages_use_published_at_then_internal_id_for_stable_pagination(): void
    {
        $owner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $first = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'First'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $second = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Second'],
            $this->totals(20_000, 3_800, 23_800),
        );
        $third = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Third'],
            $this->totals(30_000, 5_700, 35_700),
        );
        $this->publishQuote($first, 'sent', '2026-08-20 12:00:00', '2999-01-01');
        $this->publishQuote($second, 'sent', '2026-08-20 12:00:00', '2999-01-01');
        $this->publishQuote($third, 'sent', '2026-08-21 12:00:00', '2999-01-01');

        $firstPage = $repository->page(['owner_id' => (int) $owner->id], 1, 2);
        $secondPage = $repository->page(['owner_id' => (int) $owner->id], 2, 2);

        $this->assertSame([$third->id->uuid, $second->id->uuid], array_map(
            static fn ($quote): string => $quote->id->uuid,
            $firstPage->items,
        ));
        $this->assertSame([$first->id->uuid], array_map(
            static fn ($quote): string => $quote->id->uuid,
            $secondPage->items,
        ));
        $this->assertSame(3, $firstPage->total);
        $this->assertSame(3, $secondPage->total);
    }

    public function test_pages_search_the_published_current_revision_when_no_draft_exists(): void
    {
        $owner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Original draft wording'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $this->publishQuote($quote, 'sent', '2026-08-20 12:00:00', '2026-12-31');
        $seriesId = $this->seriesId($quote);
        DB::table('finance_document_revisions')
            ->where('document_series_id', $seriesId)
            ->where('id', DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('current_revision_id'))
            ->update(['snapshot' => json_encode([
                'title' => 'Published migration needle',
                'valid_until' => '2026-12-31',
            ], JSON_THROW_ON_ERROR)]);
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->delete();

        $publishedMatch = $repository->page([
            'owner_id' => (int) $owner->id,
            'q' => 'migration needle',
        ], 1, 10);
        $staleDraftMiss = $repository->page([
            'owner_id' => (int) $owner->id,
            'q' => 'original draft wording',
        ], 1, 10);

        $this->assertSame([$quote->id->uuid], array_map(
            static fn ($item): string => $item->id->uuid,
            $publishedMatch->items,
        ));
        $this->assertSame(0, $staleDraftMiss->total);
    }

    public function test_page_search_treats_sql_wildcards_as_literal_text(): void
    {
        $owner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $literalMatch = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Discount 100% exact'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Discount 1000 exact'],
            $this->totals(20_000, 3_800, 23_800),
        );

        $page = $repository->page([
            'owner_id' => (int) $owner->id,
            'q' => '100%',
        ], 1, 10);

        $this->assertSame([$literalMatch->id->uuid], array_map(
            static fn ($item): string => $item->id->uuid,
            $page->items,
        ));
    }

    public function test_pages_count_and_slice_in_the_database(): void
    {
        $owner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        foreach (range(1, 5) as $number) {
            $repository->createDraft(
                (int) $owner->id,
                ['title' => "Database page {$number}"],
                $this->totals($number * 100, $number * 19, $number * 119),
            );
        }

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'finance_quote_series')) {
                $queries[] = strtolower($query->sql);
            }
        });

        $page = $repository->page([
            'owner_id' => (int) $owner->id,
            'q' => 'database page',
        ], 2, 2);

        $this->assertSame(5, $page->total);
        $this->assertCount(2, $page->items);
        $this->assertTrue(collect($queries)->contains(
            static fn (string $sql): bool => str_contains($sql, 'count('),
        ), 'Quote pagination must execute a database count query.');
        $this->assertTrue(collect($queries)->contains(
            static fn (string $sql): bool => str_contains($sql, ' limit 2 offset 2'),
        ), 'Quote pagination must apply limit and offset in the database.');
        $this->assertTrue(collect($queries)->contains(
            static fn (string $sql): bool => str_contains($sql, 'cast(quote_document_series.uuid as text)'),
        ), 'Quote UUID search must cast PostgreSQL UUID values to portable text.');
    }

    public function test_published_date_filters_use_owner_local_day_boundaries(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill(['timezone' => 'America/New_York'])->save();
        $repository = $this->app->make(QuoteRepository::class);
        $localAugust28 = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Local August 28'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $localAugust29 = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Local August 29'],
            $this->totals(20_000, 3_800, 23_800),
        );
        $this->publishQuote($localAugust28, 'sent', '2026-08-29 03:30:00', '2026-12-31');
        $this->publishQuote($localAugust29, 'sent', '2026-08-29 04:30:00', '2026-12-31');

        $page = $repository->page([
            'owner_id' => (int) $owner->id,
            'published_from' => '2026-08-28',
            'published_to' => '2026-08-28',
        ], 1, 10);

        $this->assertSame([$localAugust28->id->uuid], array_map(
            static fn ($item): string => $item->id->uuid,
            $page->items,
        ));
    }

    public function test_effective_expiry_uses_the_injected_clock_and_owner_timezone_day_boundary(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill(['timezone' => 'Europe/Berlin'])->save();
        $this->app->instance(Clock::class, $this->clockAt('2026-08-28 23:59:59 Europe/Berlin'));
        $repository = $this->app->make(QuoteRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Day boundary'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $this->publishQuote($quote, 'sent', '2026-08-20 12:00:00', '2026-08-28');

        $this->assertSame('sent', $repository->get($quote->id)->effectiveStatus);

        $this->app->instance(Clock::class, $this->clockAt('2026-08-29 00:00:00 Europe/Berlin'));
        $nextDayRepository = $this->app->make(QuoteRepository::class);

        $this->assertSame('expired', $nextDayRepository->get($quote->id)->effectiveStatus);
    }

    public function test_queries_are_readonly_application_wrappers_around_repository_results(): void
    {
        $owner = User::factory()->create();
        $repository = $this->app->make(QuoteRepository::class);
        $quote = $repository->createDraft(
            (int) $owner->id,
            ['title' => 'Query target'],
            $this->totals(10_000, 1_900, 11_900),
        );

        $this->assertEquals($quote, new GetQuote($repository)->handle($quote->id));
        $this->assertSame(
            [$quote->id->uuid],
            array_map(
                static fn ($item): string => $item->id->uuid,
                new ListQuotes($repository)->handle(['owner_id' => (int) $owner->id], 1, 20)->items,
            ),
        );
        $this->assertSame([], new ListQuoteRevisions($repository)->handle($quote->id));
    }

    public function test_reference_resolver_rejects_foreign_numeric_partner_and_product_ids(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $ownedPartnerId = $this->insertPartner((int) $owner->id, 'Owned partner');
        $foreignPartnerId = $this->insertPartner((int) $otherOwner->id, 'Foreign partner');
        $ownedProductId = $this->insertProduct((int) $owner->id, 'Owned product');
        $foreignProductId = $this->insertProduct((int) $otherOwner->id, 'Foreign product');
        $this->actingAs($owner);
        $resolver = $this->app->make(QuoteReferenceResolver::class);

        $resolver->assertOwnedPartner(null);
        $resolver->assertOwnedPartner($ownedPartnerId);
        $resolver->assertOwnedProducts([$ownedProductId]);
        $this->addToAssertionCount(3);

        $this->assertModelNotFound(
            fn () => $resolver->assertOwnedPartner($foreignPartnerId),
        );
        $this->assertModelNotFound(
            fn () => $resolver->assertOwnedProducts([$ownedProductId, $foreignProductId]),
        );
    }

    public function test_reference_resolver_rejects_soft_deleted_partner_and_product_ids(): void
    {
        $owner = User::factory()->create();
        $partnerId = $this->insertPartner((int) $owner->id, 'Deleted partner');
        $productId = $this->insertProduct((int) $owner->id, 'Deleted product');
        DB::table('finance_partners')->where('id', $partnerId)->update(['deleted_at' => now()]);
        DB::table('finance_products')->where('id', $productId)->update(['deleted_at' => now()]);
        $this->actingAs($owner);
        $resolver = $this->app->make(QuoteReferenceResolver::class);

        $this->assertModelNotFound(fn () => $resolver->assertOwnedPartner($partnerId));
        $this->assertModelNotFound(fn () => $resolver->assertOwnedProducts([$productId]));
    }

    public function test_settings_and_clock_are_owner_explicit_and_do_not_expose_smtp_credentials(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill([
            'quote_number_format' => 'AN-YYYY-NNNN',
            'quote_next_number' => 73,
            'quote_valid_days' => 45,
            'invoice_payment_terms_days' => 21,
            'timezone' => 'Europe/Berlin',
            'company_smtp_from_name' => 'Ledgerline GmbH',
            'company_smtp_from_address' => 'quotes@example.test',
            'company_smtp_host' => 'smtp.secret.test',
            'company_smtp_username' => 'secret-user',
            'company_smtp_password' => 'secret-password',
        ])->save();
        $settings = $this->app->make(QuoteSettings::class);

        $this->assertSame('AN-YYYY-NNNN', $settings->quoteNumberFormat((int) $owner->id));
        $this->assertSame(73, $settings->quoteNumberFloor((int) $owner->id));
        $this->assertSame(45, $settings->defaultValidityDays((int) $owner->id));
        $this->assertSame(21, $settings->invoicePaymentTermsDays((int) $owner->id));
        $this->assertSame('Europe/Berlin', $settings->ownerTimezone((int) $owner->id));
        $this->assertSame([
            'name' => 'Ledgerline GmbH',
            'address' => 'quotes@example.test',
        ], $settings->senderIdentity((int) $owner->id));

        $before = new DateTimeImmutable;
        $now = $this->app->make(Clock::class)->now();
        $after = new DateTimeImmutable;

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }

    public function test_sequential_idempotency_reservations_report_new_in_progress_replay_failure_and_hash_reuse(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $quotes = $this->app->make(QuoteRepository::class);
        $quote = $quotes->createDraft(
            (int) $owner->id,
            ['title' => 'Operation target'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $operations = $this->app->make(QuoteOperationRepository::class);
        $hash = hash('sha256', '{"version":1}');

        $first = $operations->reserve(
            (int) $owner->id,
            'publish',
            'publish-key',
            $hash,
            $quote->id,
        );
        $inProgress = $operations->reserve(
            (int) $owner->id,
            'publish',
            'publish-key',
            $hash,
            $quote->id,
        );

        $this->assertSame('new', $first->status);
        $this->assertSame('in_progress', $inProgress->status);
        $this->assertSame($first->recordId, $inProgress->recordId);
        $this->assertNull($inProgress->result);

        $operations->succeed($first, ['quote_uuid' => $quote->id->uuid]);
        $replay = $operations->reserve(
            (int) $owner->id,
            'publish',
            'publish-key',
            $hash,
            $quote->id,
        );
        $this->assertSame('replay', $replay->status);
        $this->assertSame(['quote_uuid' => $quote->id->uuid], $replay->result);

        $operations->fail($first, 'late_failure');
        $stillSucceeded = $operations->reserve(
            (int) $owner->id,
            'publish',
            'publish-key',
            $hash,
            $quote->id,
        );
        $this->assertSame('replay', $stillSucceeded->status);
        $this->assertSame(['quote_uuid' => $quote->id->uuid], $stillSucceeded->result);

        try {
            $operations->reserve(
                (int) $owner->id,
                'publish',
                'publish-key',
                hash('sha256', '{"version":2}'),
                $quote->id,
            );
            $this->fail('A reused idempotency key accepted another request hash.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        $failed = $operations->reserve(
            (int) $owner->id,
            'send',
            'send-key',
            $hash,
            $quote->id,
        );
        $operations->fail($failed, 'mail_unavailable');
        $failedReplay = $operations->reserve(
            (int) $owner->id,
            'send',
            'send-key',
            $hash,
            $quote->id,
        );
        $this->assertSame('failed', $failedReplay->status);
        $this->assertSame('mail_unavailable', $failedReplay->errorCode);

        $operations->succeed($failed, ['unexpected' => true]);
        $stillFailed = $operations->reserve(
            (int) $owner->id,
            'send',
            'send-key',
            $hash,
            $quote->id,
        );
        $this->assertSame('failed', $stillFailed->status);
        $this->assertSame('mail_unavailable', $stillFailed->errorCode);

        $otherOwnerReservation = $operations->reserve(
            (int) $otherOwner->id,
            'publish',
            'publish-key',
            $hash,
            null,
        );
        $this->assertSame('new', $otherOwnerReservation->status);
    }

    public function test_postgresql_unique_conflict_path_reloads_the_existing_reservation_when_enabled(): void
    {
        if (DB::getDriverName() !== 'pgsql'
            || getenv('FINANCE_QUOTE_PG_CONFLICT_TEST') !== '1') {
            $this->markTestSkipped(
                'Set FINANCE_QUOTE_PG_CONFLICT_TEST=1 and run the suite on PostgreSQL to exercise its unique-conflict path.',
            );
        }

        $owner = User::factory()->create();
        $operations = $this->app->make(QuoteOperationRepository::class);
        $hash = hash('sha256', '{"postgresql":true}');
        $first = $operations->reserve(
            (int) $owner->id,
            'publish',
            'postgresql-conflict-key',
            $hash,
            null,
        );
        $conflict = $operations->reserve(
            (int) $owner->id,
            'publish',
            'postgresql-conflict-key',
            $hash,
            null,
        );

        $this->assertSame('new', $first->status);
        $this->assertSame('in_progress', $conflict->status);
        $this->assertSame($first->recordId, $conflict->recordId);
    }

    public function test_idempotency_key_reuse_is_bound_to_the_same_quote_series(): void
    {
        $owner = User::factory()->create();
        $quotes = $this->app->make(QuoteRepository::class);
        $firstQuote = $quotes->createDraft(
            (int) $owner->id,
            ['title' => 'First operation target'],
            $this->totals(10_000, 1_900, 11_900),
        );
        $secondQuote = $quotes->createDraft(
            (int) $owner->id,
            ['title' => 'Second operation target'],
            $this->totals(20_000, 3_800, 23_800),
        );
        $operations = $this->app->make(QuoteOperationRepository::class);
        $hash = hash('sha256', '{"version":1}');

        $operations->reserve((int) $owner->id, 'publish', 'quote-bound-key', $hash, $firstQuote->id);

        foreach ([$secondQuote->id, null] as $differentTarget) {
            try {
                $operations->reserve(
                    (int) $owner->id,
                    'publish',
                    'quote-bound-key',
                    $hash,
                    $differentTarget,
                );
                $this->fail('A reused idempotency key accepted another quote target.');
            } catch (DomainException $exception) {
                $this->assertSame('idempotency_key_reused', $exception->getMessage());
            }
        }
    }

    public function test_idempotency_reservation_validates_its_portable_database_inputs(): void
    {
        $owner = User::factory()->create();
        $operations = $this->app->make(QuoteOperationRepository::class);
        $hash = hash('sha256', '{}');
        $invalidInputs = [
            [0, 'publish', 'key', $hash],
            [(int) $owner->id, '', 'key', $hash],
            [(int) $owner->id, str_repeat('o', 65), 'key', $hash],
            [(int) $owner->id, 'publish', '   ', $hash],
            [(int) $owner->id, 'publish', str_repeat('k', 256), $hash],
            [(int) $owner->id, 'publish', 'key', strtoupper($hash)],
            [(int) $owner->id, 'publish', 'key', str_repeat('g', 64)],
            [(int) $owner->id, 'publish', 'key', str_repeat('a', 63)],
        ];

        foreach ($invalidInputs as [$ownerId, $operation, $key, $requestSha256]) {
            try {
                $operations->reserve($ownerId, $operation, $key, $requestSha256, null);
                $this->fail('An invalid operation reservation input reached persistence.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('finance_quote_operations')->count());
    }

    public function test_idempotency_reservation_requires_quote_id_owner_to_match_the_explicit_owner(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $quote = $this->app->make(QuoteRepository::class)->createDraft(
            (int) $owner->id,
            ['title' => 'Owner-bound operation target'],
            $this->totals(10_000, 1_900, 11_900),
        );

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(QuoteOperationRepository::class)->reserve(
            (int) $owner->id,
            'publish',
            'mismatched-owner-key',
            hash('sha256', '{}'),
            new QuoteId((int) $otherOwner->id, $quote->id->uuid),
        );
    }

    public function test_idempotency_reservation_rejects_a_quote_owned_by_another_owner(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $quote = $this->app->make(QuoteRepository::class)->createDraft(
            (int) $otherOwner->id,
            ['title' => 'Foreign operation target'],
            $this->totals(10_000, 1_900, 11_900),
        );

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(QuoteOperationRepository::class)->reserve(
            (int) $owner->id,
            'publish',
            'foreign-key',
            hash('sha256', '{}'),
            $quote->id,
        );
    }

    private function totals(int $net, int $vat, int $gross): DocumentTotals
    {
        return new DocumentTotals(
            Money::fromMinor($net, 'EUR'),
            Money::fromMinor($vat, 'EUR'),
            Money::fromMinor($gross, 'EUR'),
            Money::fromMinor(0, 'EUR'),
            [],
        );
    }

    private function insertRevision(int $ownerId, int $seriesId, int $number, ?int $previousId): int
    {
        return (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $ownerId,
            'document_series_id' => $seriesId,
            'revision_number' => $number,
            'previous_revision_id' => $previousId,
            'status' => 'published',
            'snapshot' => json_encode([
                'title' => "Revision {$number}",
                'valid_until' => '2026-12-31',
            ], JSON_THROW_ON_ERROR),
            'net_minor' => $number * 10_000,
            'vat_minor' => $number * 1_900,
            'gross_minor' => $number * 11_900,
            'currency' => 'EUR',
            'change_reason' => null,
            'pdf_path' => "finance/quotes/revision-{$number}.pdf",
            'pdf_sha256' => str_repeat((string) $number, 64),
            'published_at' => "2026-08-2{$number} 10:00:00",
            'created_by' => $ownerId,
            'created_at' => "2026-08-2{$number} 10:00:00",
        ]);
    }

    private function publishQuote(
        QuoteView $quote,
        string $status,
        string $publishedAt,
        string $validUntil,
    ): void {
        $seriesId = $this->seriesId($quote);
        $revisionId = $this->insertRevision($quote->id->ownerId, $seriesId, 1, null);
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'snapshot' => json_encode([
                'title' => $quote->draft['title'],
                'valid_until' => $validUntil,
            ], JSON_THROW_ON_ERROR),
            'published_at' => $publishedAt,
        ]);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
            'published_at' => $publishedAt,
        ]);
        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => $status]);
    }

    private function seriesId(QuoteView $quote): int
    {
        return (int) DB::table('finance_document_series')
            ->where('user_id', $quote->id->ownerId)
            ->where('uuid', $quote->id->uuid)
            ->value('id');
    }

    private function insertPartner(int $ownerId, string $name): int
    {
        return (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $ownerId,
            'name' => $name,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProduct(int $ownerId, string $name): int
    {
        return (int) DB::table('finance_products')->insertGetId([
            'user_id' => $ownerId,
            'kind' => 'service',
            'name' => $name,
            'price_net' => 100,
            'active' => true,
            'track_stock' => false,
            'stock_qty' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function clockAt(string $at): Clock
    {
        return new readonly class($at) implements Clock
        {
            private DateTimeImmutable $now;

            public function __construct(string $at)
            {
                $this->now = new DateTimeImmutable($at);
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    /** @param callable(): void $callback */
    private function assertModelNotFound(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an owner-scoped lookup to fail.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
