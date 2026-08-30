<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Quotes\CreateQuote;
use App\Modules\Finance\Application\Commands\Quotes\DiscardQuoteDraft;
use App\Modules\Finance\Application\Commands\Quotes\UpdateQuoteDraft;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Queries\Quotes\PreviewQuoteTotals;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteOperationRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use TypeError;

final class QuoteDraftApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_parses_exact_decimals_applies_owner_date_defaults_and_returns_authoritative_totals(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill([
            'timezone' => 'Europe/Berlin',
            'quote_valid_days' => 30,
        ])->save();
        $this->app->instance(Clock::class, $this->clockAt('2026-08-28 22:30:00 UTC'));
        $this->actingAs($owner);

        $preview = $this->app->make(PreviewQuoteTotals::class)->handle(
            (int) $owner->id,
            $this->draft(issueDate: null, validUntil: null),
        );

        $this->assertSame(22_500, $preview->netMinor);
        $this->assertSame(4_275, $preview->vatMinor);
        $this->assertSame(26_775, $preview->grossMinor);
        $this->assertSame(2_500, $preview->discountMinor);
        $this->assertSame('EUR', $preview->currency);
        $this->assertSame([[
            'tax_rate_basis_points' => 1900,
            'net_minor' => 22_500,
            'vat_minor' => 4_275,
            'gross_minor' => 26_775,
        ]], $preview->taxBreakdowns);
        $this->assertSame('2026-08-29', $preview->issueDate);
        $this->assertSame('2026-09-28', $preview->validUntil);
    }

    public function test_preview_with_default_settings_is_read_only(): void
    {
        $owner = User::factory()->create();
        $this->app->instance(Clock::class, $this->clockAt('2026-08-28 22:30:00 UTC'));
        $this->actingAs($owner);

        $this->assertSame(0, DB::table('user_settings')->where('user_id', $owner->id)->count());

        $preview = $this->app->make(PreviewQuoteTotals::class)->handle(
            (int) $owner->id,
            $this->draft(issueDate: null, validUntil: null),
        );

        $this->assertSame('2026-08-28', $preview->issueDate);
        $this->assertSame('2026-09-27', $preview->validUntil);
        $this->assertSame(0, DB::table('user_settings')->where('user_id', $owner->id)->count());
    }

    public function test_input_rejects_json_numbers_invalid_scales_empty_or_excessive_lines_and_invalid_discounts(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $preview = $this->app->make(PreviewQuoteTotals::class);

        try {
            new QuoteLineData('Consulting', 2.5, 'hour', '100.00', '19.00', 'service', null);
            $this->fail('A JSON numeric quantity crossed the string input boundary.');
        } catch (TypeError) {
            $this->addToAssertionCount(1);
        }

        foreach ([
            $this->draft(lines: []),
            $this->draft(lines: array_fill(0, 201, $this->line())),
            $this->draft(lines: [$this->line(quantity: '1.00001')]),
            $this->draft(lines: [$this->line(unitPrice: '1.001')]),
            $this->draft(lines: [$this->line(taxRate: '19.001')]),
            $this->draft(discountType: 'percent', discountValue: '100.01'),
            $this->draft(discountType: 'fixed', discountValue: '999999.00'),
        ] as $invalid) {
            try {
                $preview->handle((int) $owner->id, $invalid);
                $this->fail('Invalid quote input produced an authoritative preview.');
            } catch (DomainException|InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_control_totals_are_checks_only_and_never_replace_server_totals(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $preview = $this->app->make(PreviewQuoteTotals::class);

        $matching = $preview->handle((int) $owner->id, $this->draft(
            controlNetMinor: 22_500,
            controlVatMinor: 4_275,
            controlGrossMinor: 26_775,
        ));
        $this->assertSame(26_775, $matching->grossMinor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('control_totals_mismatch');
        $preview->handle((int) $owner->id, $this->draft(
            controlNetMinor: 1,
            controlVatMinor: 2,
            controlGrossMinor: 3,
        ));
    }

    public function test_preview_rejects_foreign_or_deleted_partner_and_product_references(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignPartnerId = $this->insertPartner((int) $otherOwner->id, 'Foreign partner');
        $deletedPartnerId = $this->insertPartner((int) $owner->id, 'Deleted partner');
        $foreignProductId = $this->insertProduct((int) $otherOwner->id, 'Foreign product');
        $deletedProductId = $this->insertProduct((int) $owner->id, 'Deleted product');
        DB::table('finance_partners')->where('id', $deletedPartnerId)->update(['deleted_at' => now()]);
        DB::table('finance_products')->where('id', $deletedProductId)->update(['deleted_at' => now()]);
        $this->actingAs($owner);
        $preview = $this->app->make(PreviewQuoteTotals::class);

        foreach ([$foreignPartnerId, $deletedPartnerId] as $partnerId) {
            $this->assertModelNotFound(fn () => $preview->handle(
                (int) $owner->id,
                $this->draft(partnerId: $partnerId),
            ));
        }
        foreach ([$foreignProductId, $deletedProductId] as $productId) {
            $this->assertModelNotFound(fn () => $preview->handle(
                (int) $owner->id,
                $this->draft(lines: [$this->line(productId: $productId)]),
            ));
        }
    }

    public function test_create_is_idempotent_and_persists_only_the_authoritative_snapshot_and_activity(): void
    {
        $owner = User::factory()->create();
        $partnerId = $this->insertPartner((int) $owner->id, 'Owned partner');
        $productId = $this->insertProduct((int) $owner->id, 'Owned product');
        $this->actingAs($owner);
        $command = $this->app->make(CreateQuote::class);
        $data = $this->draft(
            partnerId: $partnerId,
            lines: [$this->line(productId: $productId)],
            controlNetMinor: 22_500,
            controlVatMinor: 4_275,
            controlGrossMinor: 26_775,
        );

        $created = $command->handle((int) $owner->id, 'create-draft-key', $data);
        $replay = $command->handle((int) $owner->id, 'create-draft-key', $data);

        $this->assertSame($created->id->uuid, $replay->id->uuid);
        $this->assertSame($partnerId, $created->partnerId);
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
        $this->assertSame(['quote.created'], DB::table('finance_document_activities')
            ->where('user_id', $owner->id)
            ->pluck('type')
            ->all());
        $payload = $created->draft;
        $this->assertIsArray($payload);
        $this->assertSame(25000, $payload['lines'][0]['quantity_scaled']);
        $this->assertSame(10000, $payload['lines'][0]['unit_price_minor']);
        $this->assertSame(1900, $payload['lines'][0]['tax_rate_basis_points']);
        $this->assertSame([
            'net_minor' => 22_500,
            'vat_minor' => 4_275,
            'gross_minor' => 26_775,
            'discount_minor' => 2_500,
            'currency' => 'EUR',
            'tax_breakdowns' => [[
                'tax_rate_basis_points' => 1900,
                'net_minor' => 22_500,
                'vat_minor' => 4_275,
                'gross_minor' => 26_775,
            ]],
        ], $payload['totals']);
        $this->assertArrayNotHasKey('control_net_minor', $payload);

        try {
            $command->handle(
                (int) $owner->id,
                'create-draft-key',
                $this->draft(title: 'Different request'),
            );
            $this->fail('An idempotency key accepted a different canonical request.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }
    }

    public function test_create_completion_failure_rolls_back_and_same_key_retry_creates_exactly_once(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $command = $this->app->make(CreateQuote::class);
        $data = $this->draft();
        QuoteOperationRecord::updating(static function (QuoteOperationRecord $operation): void {
            if ($operation->operation === 'create'
                && $operation->state === 'succeeded') {
                throw new \RuntimeException('injected quote operation completion failure');
            }
        });

        try {
            $command->handle((int) $owner->id, 'retry-after-completion-failure', $data);
            $this->fail('The injected operation completion failure was not observed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('injected quote operation completion failure', $exception->getMessage());
        } finally {
            QuoteOperationRecord::flushEventListeners();
        }

        $this->assertSame(0, DB::table('finance_document_series')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_quote_drafts')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_document_activities')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_quote_operations')->where('user_id', $owner->id)->count());

        $retried = $command->handle((int) $owner->id, 'retry-after-completion-failure', $data);
        $replayed = $command->handle((int) $owner->id, 'retry-after-completion-failure', $data);

        $this->assertSame($retried->id->uuid, $replayed->id->uuid);
        $this->assertSame(1, DB::table('finance_document_series')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_quote_drafts')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_document_activities')->where('user_id', $owner->id)->count());
        $operation = QuoteOperationRecord::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $owner->id)
            ->sole();
        $this->assertSame('succeeded', $operation->state);
        $operationSeriesId = $operation->getAttribute('document_series_id');
        $this->assertIsInt($operationSeriesId);
        $this->assertSame($this->seriesId($retried), $operationSeriesId);
        $result = $operation->getAttribute('result');
        $this->assertIsArray($result);
        $this->assertSame(
            ['quote_uuid' => $retried->id->uuid],
            $result,
        );
    }

    public function test_create_idempotency_hash_is_stable_when_default_dates_change_after_owner_midnight(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill([
            'timezone' => 'Europe/Berlin',
            'quote_valid_days' => 30,
        ])->save();
        $this->actingAs($owner);
        $data = $this->draft(issueDate: null, validUntil: null);
        $this->app->instance(Clock::class, $this->clockAt('2026-08-28 21:30:00 UTC'));
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'midnight-stable-key',
            $data,
        );

        $this->app->instance(Clock::class, $this->clockAt('2026-08-28 22:30:00 UTC'));
        $replay = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'midnight-stable-key',
            $data,
        );

        $this->assertSame($created->id->uuid, $replay->id->uuid);
        $this->assertSame('2026-08-28', $replay->draft['issue_date']);
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
    }

    public function test_create_replay_does_not_revalidate_references_that_were_live_for_the_original_write(): void
    {
        $owner = User::factory()->create();
        $partnerId = $this->insertPartner((int) $owner->id, 'Original partner');
        $productId = $this->insertProduct((int) $owner->id, 'Original product');
        $this->actingAs($owner);
        $data = $this->draft(
            partnerId: $partnerId,
            lines: [$this->line(productId: $productId)],
        );
        $command = $this->app->make(CreateQuote::class);
        $created = $command->handle((int) $owner->id, 'reference-replay-key', $data);
        DB::table('finance_partners')->where('id', $partnerId)->update(['deleted_at' => now()]);
        DB::table('finance_products')->where('id', $productId)->update(['deleted_at' => now()]);

        $replay = $command->handle((int) $owner->id, 'reference-replay-key', $data);

        $this->assertSame($created->id->uuid, $replay->id->uuid);
        $this->assertSame(1, DB::table('finance_quote_series')->where('user_id', $owner->id)->count());
    }

    public function test_update_uses_compare_and_swap_and_emits_one_activity_only_for_the_winner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-for-update',
            $this->draft(title: 'Original'),
        );
        $command = $this->app->make(UpdateQuoteDraft::class);

        $updated = $command->handle(
            $created->id,
            expectedVersion: 0,
            data: $this->draft(title: 'Winner'),
        );
        $stale = $command->handle(
            $created->id,
            expectedVersion: 0,
            data: $this->draft(title: 'Loser'),
        );

        $this->assertSame(1, $updated->version);
        $this->assertSame('Winner', $updated->draft['title']);
        $this->assertSame(1, $stale->version);
        $this->assertSame('Winner', $stale->draft['title']);
        $this->assertSame(
            ['quote.created', 'quote.draft.updated'],
            DB::table('finance_document_activities')
                ->where('user_id', $owner->id)
                ->orderBy('id')
                ->pluck('type')
                ->all(),
        );
    }

    public function test_discard_rejects_the_only_initial_draft_but_removes_a_later_version_draft_atomically(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-for-discard',
            $this->draft(title: 'Initial'),
        );
        $command = $this->app->make(DiscardQuoteDraft::class);

        try {
            $command->handle($created->id, 0);
            $this->fail('The only initial draft was discarded.');
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame('initial_draft_cannot_be_discarded', $exception->errorCode);
        }

        $revisionId = $this->makeCurrentRevision($created);
        DB::table('finance_quote_drafts')
            ->where('document_series_id', $this->seriesId($created))
            ->update(['based_on_revision_id' => $revisionId]);

        $discarded = $command->handle($created->id, 0);
        $staleReplay = $command->handle($created->id, 0);

        $this->assertNull($discarded->draft);
        $this->assertSame(1, $discarded->version);
        $this->assertNull($staleReplay->draft);
        $this->assertSame(0, DB::table('finance_quote_drafts')
            ->where('document_series_id', $this->seriesId($created))
            ->count());
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('document_series_id', $this->seriesId($created))
            ->where('type', 'quote.draft.discarded')
            ->count());
    }

    /** @param list<QuoteLineData>|null $lines */
    private function draft(
        string $title = 'Network refresh',
        ?int $partnerId = null,
        ?string $issueDate = '2026-08-28',
        ?string $validUntil = '2026-09-27',
        ?array $lines = null,
        string $discountType = 'percent',
        ?string $discountValue = '10.00',
        ?int $controlNetMinor = null,
        ?int $controlVatMinor = null,
        ?int $controlGrossMinor = null,
    ): QuoteDraftData {
        return new QuoteDraftData(
            title: $title,
            partnerId: $partnerId,
            customer: ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            issueDate: $issueDate,
            validUntil: $validUntil,
            currency: 'EUR',
            lines: $lines ?? [$this->line()],
            discountType: $discountType,
            discountValue: $discountValue,
            introText: null,
            outroText: null,
            internalNote: null,
            controlNetMinor: $controlNetMinor,
            controlVatMinor: $controlVatMinor,
            controlGrossMinor: $controlGrossMinor,
        );
    }

    private function line(
        string $quantity = '2.5000',
        string $unitPrice = '100.00',
        string $taxRate = '19.00',
        ?int $productId = null,
    ): QuoteLineData {
        return new QuoteLineData(
            'Consulting',
            $quantity,
            'hour',
            $unitPrice,
            $taxRate,
            'service',
            $productId,
        );
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

    private function makeCurrentRevision(QuoteView $quote): int
    {
        $seriesId = $this->seriesId($quote);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $quote->id->ownerId,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode($quote->draft, JSON_THROW_ON_ERROR),
            'net_minor' => $quote->netMinor,
            'vat_minor' => $quote->vatMinor,
            'gross_minor' => $quote->grossMinor,
            'currency' => $quote->currency,
            'change_reason' => null,
            'pdf_path' => 'finance/quotes/current.pdf',
            'pdf_sha256' => hash('sha256', 'pdf'),
            'published_at' => now(),
            'created_by' => $quote->id->ownerId,
            'created_at' => now(),
        ]);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
            'published_at' => now(),
        ]);
        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => 'sent']);

        return $revisionId;
    }

    private function seriesId(QuoteView $quote): int
    {
        return (int) DB::table('finance_document_series')
            ->where('user_id', $quote->id->ownerId)
            ->where('uuid', $quote->id->uuid)
            ->value('id');
    }

    private function assertModelNotFound(callable $operation): void
    {
        try {
            $operation();
            $this->fail('An owner-scoped reference unexpectedly resolved.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    private function clockAt(string $at): Clock
    {
        return new readonly class($at) implements Clock
        {
            public function __construct(private string $at) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->at);
            }
        };
    }
}
