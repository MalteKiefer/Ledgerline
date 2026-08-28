<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
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

    public function test_idempotency_reservations_report_new_in_progress_replay_failure_and_hash_reuse(): void
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
        mixed $quote,
        string $status,
        string $publishedAt,
        string $validUntil,
    ): void {
        $seriesId = (int) DB::table('finance_document_series')
            ->where('user_id', $quote->id->ownerId)
            ->where('uuid', $quote->id->uuid)
            ->value('id');
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
