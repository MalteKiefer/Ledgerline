<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\FinanceProduct;
use App\Models\FinanceStockMovement;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Quotes\AcceptQuote;
use App\Modules\Finance\Application\Commands\Quotes\ConvertQuoteToInvoice;
use App\Modules\Finance\Application\Commands\Quotes\CreateQuote;
use App\Modules\Finance\Application\Commands\Quotes\DeclineQuote;
use App\Modules\Finance\Application\Commands\Quotes\DiscardQuoteDraft;
use App\Modules\Finance\Application\Commands\Quotes\DuplicateQuote;
use App\Modules\Finance\Application\Commands\Quotes\StartQuoteVersion;
use App\Modules\Finance\Application\Commands\Quotes\UpdateQuoteDraft;
use App\Modules\Finance\Application\DTOs\Quotes\ConvertQuoteToInvoiceData;
use App\Modules\Finance\Application\DTOs\Quotes\DecideQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\DuplicateQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteToInvoicePort;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDraftAdapter;
use App\Services\Finance\FinanceReports;
use App\Services\Finance\StockLedger;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class QuoteDecisionConversionTest extends TestCase
{
    use RefreshDatabase;

    private QuoteDecisionClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new QuoteDecisionClock(new DateTimeImmutable('2026-09-27 21:59:59.999999+00:00'));
        $this->app->instance(Clock::class, $this->clock);
    }

    public function test_accept_and_decline_are_atomic_timestamped_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $accepted = $this->publishedQuote($owner, '2026-09-27');
        $declined = $this->publishedQuote($owner, '2026-09-27', 'decline');

        $acceptData = new DecideQuoteData($accepted->id, 0, $accepted->currentRevision->id, 'accept-key');
        $first = $this->app->make(AcceptQuote::class)->handle($acceptData);
        $replay = $this->app->make(AcceptQuote::class)->handle($acceptData);
        $declinedView = $this->app->make(DeclineQuote::class)->handle(new DecideQuoteData(
            $declined->id,
            0,
            $declined->currentRevision->id,
            'decline-key',
        ));

        $this->assertSame('accepted', $first->status);
        $this->assertEquals($first->acceptedAt, $replay->acceptedAt);
        $this->assertSame('declined', $declinedView->status);
        $this->assertNotNull($declinedView->declinedAt);
        $this->assertSame(1, $this->activityCount($accepted, 'quote.accepted'));
        $this->assertSame(1, $this->activityCount($declined, 'quote.declined'));
        $this->assertSame(2, DB::table('finance_quote_operations')
            ->whereIn('operation', ['accept', 'decline'])
            ->where('state', 'succeeded')->count());

        $this->expectQuoteError('invalid_transition', fn () => $this->app->make(DeclineQuote::class)->handle(
            new DecideQuoteData($accepted->id, 1, $accepted->currentRevision->id, 'decline-after-accept'),
        ));
    }

    public function test_decisions_reject_pending_stale_replaced_and_expired_revisions_without_mutation(): void
    {
        $owner = User::factory()->create();
        $pending = $this->publishedQuote($owner, '2026-09-27', 'pending');
        DB::table('finance_quote_drafts')->insert([
            'document_series_id' => $this->seriesId($pending), 'user_id' => $owner->id,
            'based_on_revision_id' => $pending->currentRevision->id,
            'payload' => json_encode($pending->currentRevision->snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $pending->netMinor, 'vat_minor' => $pending->vatMinor,
            'gross_minor' => $pending->grossMinor, 'currency' => 'EUR',
            'updated_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectQuoteError('quote_draft_pending', fn () => $this->app->make(AcceptQuote::class)->handle(
            new DecideQuoteData($pending->id, 0, $pending->currentRevision->id, 'pending-key'),
        ));

        $stale = $this->publishedQuote($owner, '2026-09-27', 'stale');
        $this->expectQuoteError('quote_revision_stale', fn () => $this->app->make(AcceptQuote::class)->handle(
            new DecideQuoteData($stale->id, 0, $stale->currentRevision->id + 10_000, 'stale-key'),
        ));

        [$replaced, $oldRevisionId] = $this->quoteWithReplacedRevision($owner);
        $this->expectQuoteError('quote_revision_replaced', fn () => $this->app->make(AcceptQuote::class)->handle(
            new DecideQuoteData($replaced->id, 0, $oldRevisionId, 'replaced-key'),
        ));

        $expired = $this->publishedQuote($owner, '2026-09-26', 'expired');
        $this->expectQuoteError('quote_expired', fn () => $this->app->make(AcceptQuote::class)->handle(
            new DecideQuoteData($expired->id, 0, $expired->currentRevision->id, 'expired-key'),
        ));
        $stored = $this->repository()->get($expired->id);
        $this->assertSame('sent', $stored->status);
        $this->assertSame('expired', $stored->effectiveStatus);
    }

    public function test_unpublished_draft_cannot_be_decided_or_converted(): void
    {
        $owner = User::factory()->create();
        $draft = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'unpublished-source',
            $this->draft(issueDate: '2026-08-28', validUntil: '2026-09-27'),
        );

        $this->expectQuoteError('quote_not_published', fn () => $this->app->make(AcceptQuote::class)->handle(
            new DecideQuoteData($draft->id, 0, 999, 'accept-unpublished'),
        ));
        $this->expectQuoteError('quote_not_published', fn () => $this->convertCommand(
            new RecordingQuoteToInvoicePort,
        )->handle(new ConvertQuoteToInvoiceData($draft->id, 0, 999, 'convert-unpublished')));
    }

    public function test_validity_is_inclusive_through_owner_local_end_of_day(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill(['timezone' => 'Europe/Berlin'])->save();
        $quote = $this->publishedQuote($owner, '2026-09-27');

        $accepted = $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
            $quote->id,
            0,
            $quote->currentRevision->id,
            'boundary-key',
        ));

        $this->assertSame('accepted', $accepted->status);
    }

    public function test_duplicate_recalculates_selected_snapshot_as_an_independent_unnumbered_draft(): void
    {
        $owner = User::factory()->create();
        UserSetting::for((int) $owner->id)->forceFill([
            'timezone' => 'Europe/Berlin',
            'quote_valid_days' => 14,
        ])->save();
        $source = $this->publishedQuote($owner, '2026-09-27');
        DB::table('finance_document_revisions')->where('id', $source->currentRevision->id)->update([
            'snapshot' => json_encode([
                ...$source->currentRevision->snapshot,
                'totals' => ['net_minor' => 1, 'vat_minor' => 2, 'gross_minor' => 3, 'currency' => 'EUR'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $data = new DuplicateQuoteData($source->id, 0, $source->currentRevision->id, 'duplicate-key');

        $first = $this->app->make(DuplicateQuote::class)->handle($data);
        $replay = $this->app->make(DuplicateQuote::class)->handle($data);
        $second = $this->app->make(DuplicateQuote::class)->handle(new DuplicateQuoteData(
            $source->id, 0, $source->currentRevision->id, 'duplicate-key-2',
        ));

        $this->assertSame($first->id->uuid, $replay->id->uuid);
        $this->assertNotSame($first->id->uuid, $second->id->uuid);
        $this->assertSame('draft', $first->status);
        $this->assertNull($first->number);
        $this->assertNull($first->currentRevision);
        $this->assertSame('2026-09-27', $first->draft['issue_date']);
        $this->assertSame('2026-10-11', $first->draft['valid_until']);
        $this->assertSame(22_500, $first->netMinor);
        $this->assertSame(26_775, $first->grossMinor);
        $this->assertSame($source->currentRevision->snapshot['customer'], $first->draft['customer']);
        $this->assertSame($source->currentRevision->snapshot['lines'], $first->draft['lines']);
        $this->assertSame($source->currentRevision->snapshot['discount'], $first->draft['discount']);
        $this->assertSame('Intro', $first->draft['intro_text']);
        $this->assertSame('Outro', $first->draft['outro_text']);
        $this->assertSame('Internal only', $first->draft['internal_note']);

        $firstSource = DB::table('finance_document_series')
            ->where('user_id', $owner->id)
            ->where('uuid', $first->id->uuid)
            ->first(['source_type', 'source_id']);
        $secondSource = DB::table('finance_document_series')
            ->where('user_id', $owner->id)
            ->where('uuid', $second->id->uuid)
            ->first(['source_type', 'source_id']);
        $this->assertSame('quote_duplicate_operation', $firstSource?->source_type);
        $this->assertIsInt($firstSource?->source_id);
        $this->assertSame('quote_duplicate_operation', $secondSource?->source_type);
        $this->assertNotSame($firstSource?->source_id, $secondSource?->source_id);
        $this->assertSame($this->seriesId($source), (int) DB::table('finance_quote_operations')
            ->where('user_id', $owner->id)
            ->where('id', $firstSource?->source_id)
            ->value('document_series_id'));

        $otherOwner = User::factory()->create();
        $foreignOperationId = (int) DB::table('finance_quote_operations')->insertGetId([
            'user_id' => $otherOwner->id,
            'document_series_id' => null,
            'operation' => 'duplicate',
            'idempotency_key' => 'foreign-provenance',
            'request_sha256' => str_repeat('a', 64),
            'state' => 'reserved',
            'result' => null,
            'error_code' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);
        $this->assertModelNotFound(fn () => $this->repository()->createDraft(
            (int) $owner->id,
            $first->draft,
            new DocumentTotals(
                Money::fromMinor($first->netMinor, $first->currency),
                Money::fromMinor($first->vatMinor, $first->currency),
                Money::fromMinor($first->grossMinor, $first->currency),
                Money::fromMinor(0, $first->currency),
                [],
            ),
            $first->partnerId,
            'quote_duplicate_operation',
            $foreignOperationId,
        ));
    }

    public function test_initial_draft_can_be_duplicated_but_wrong_selected_revision_cannot(): void
    {
        $owner = User::factory()->create();
        $source = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'initial-source',
            $this->draft(issueDate: '2026-08-28', validUntil: '2026-09-27'),
        );

        $copy = $this->app->make(DuplicateQuote::class)->handle(new DuplicateQuoteData(
            $source->id, 0, null, 'duplicate-initial',
        ));
        $this->assertSame('Network refresh', $copy->draft['title']);

        $this->expectQuoteError('quote_revision_stale', fn () => $this->app->make(DuplicateQuote::class)->handle(
            new DuplicateQuoteData($source->id, 0, 99_999, 'duplicate-invalid'),
        ));
    }

    public function test_duplicate_uses_the_selected_revision_partner_after_a_later_draft_changed_and_discarded_it(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $oldPartnerId = $this->insertPartner((int) $owner->id, 'Original partner');
        $laterPartnerId = $this->insertPartner((int) $owner->id, 'Later draft partner');
        $source = $this->publishedQuote(
            $owner,
            '2026-09-27',
            'partner-history',
            $this->draft('2026-08-28', '2026-09-27', partnerId: $oldPartnerId),
        );

        $started = $this->app->make(StartQuoteVersion::class)->handle($source->id, 0);
        $changed = $this->app->make(UpdateQuoteDraft::class)->handle(
            id: $source->id,
            expectedVersion: $started->version,
            data: $this->draft('2026-08-28', '2026-09-27', partnerId: $laterPartnerId),
        );
        $discarded = $this->app->make(DiscardQuoteDraft::class)->handle($source->id, $changed->version);
        $this->assertSame($laterPartnerId, $discarded->partnerId);
        $this->assertNull($discarded->draft);

        $copy = $this->app->make(DuplicateQuote::class)->handle(new DuplicateQuoteData(
            $source->id,
            $discarded->version,
            $source->currentRevision->id,
            'duplicate-selected-revision-partner',
        ));

        $this->assertSame($oldPartnerId, $source->currentRevision->snapshot['partner_id']);
        $this->assertSame($oldPartnerId, $copy->partnerId);
        $this->assertSame($oldPartnerId, $copy->draft['partner_id']);
    }

    public function test_conversion_requires_accepted_current_nonexpired_revision_and_replays_one_target(): void
    {
        $owner = User::factory()->create();
        $quote = $this->publishedQuote($owner, '2026-09-27');
        $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
            $quote->id, 0, $quote->currentRevision->id, 'accept-convert',
        ));
        $port = new RecordingQuoteToInvoicePort;
        $command = $this->convertCommand($port);
        $data = new ConvertQuoteToInvoiceData($quote->id, 1, $quote->currentRevision->id, 'convert-key');

        $first = $command->handle($data);
        $replay = $command->handle($data);
        $differentKey = $command->handle(new ConvertQuoteToInvoiceData(
            $quote->id, 1, $quote->currentRevision->id, 'convert-other-key',
        ));

        $this->assertEquals($first, $replay);
        $this->assertEquals($first, $differentKey);
        $this->assertSame(1, $port->calls);
        $this->assertSame($quote->id->uuid, $port->source?->snapshot['series_uuid']);
        $this->assertSame($quote->currentRevision->id, $port->source?->id);
        $this->assertSame($quote->currentRevision->snapshot, $port->snapshot);
        $this->assertSame(hash('sha256', json_encode($port->snapshot, JSON_THROW_ON_ERROR)), $port->source?->canonicalSnapshotSha256());
        $converted = $this->repository()->get($quote->id);
        $this->assertSame('converted', $converted->status);
        $this->assertNotNull($converted->convertedAt);
        $this->assertSame(1, DB::table('finance_quote_conversions')->count());
        $this->assertSame(1, $this->activityCount($quote, 'quote.converted'));
    }

    public function test_conversion_rejections_leave_no_target_conversion_or_activity(): void
    {
        $owner = User::factory()->create();
        $sent = $this->publishedQuote($owner, '2026-09-27');
        $port = new RecordingQuoteToInvoicePort;

        $this->expectQuoteError('quote_not_accepted', fn () => $this->convertCommand($port)->handle(
            new ConvertQuoteToInvoiceData($sent->id, 0, $sent->currentRevision->id, 'not-accepted'),
        ));
        $this->assertSame(0, $port->calls);
        $this->assertSame(0, DB::table('finance_quote_conversions')->count());
    }

    public function test_conversion_rejects_declined_expired_replaced_and_pending_draft_sources(): void
    {
        $owner = User::factory()->create();
        $port = new RecordingQuoteToInvoicePort;

        $declined = $this->publishedQuote($owner, '2026-09-27', 'declined-conversion');
        $this->app->make(DeclineQuote::class)->handle(new DecideQuoteData(
            $declined->id, 0, $declined->currentRevision->id, 'decline-for-conversion',
        ));
        $this->expectQuoteError('quote_not_accepted', fn () => $this->convertCommand($port)->handle(
            new ConvertQuoteToInvoiceData($declined->id, 1, $declined->currentRevision->id, 'convert-declined'),
        ));

        $expired = $this->publishedQuote($owner, '2026-09-26', 'expired-conversion');
        $this->forceAccepted($expired);
        $this->assertSame('accepted', $this->repository()->get($expired->id)->status);
        $this->assertSame('expired', $this->repository()->get($expired->id)->effectiveStatus);
        $this->expectQuoteError('quote_expired', fn () => $this->convertCommand($port)->handle(
            new ConvertQuoteToInvoiceData($expired->id, 1, $expired->currentRevision->id, 'convert-expired'),
        ));

        $pending = $this->publishedQuote($owner, '2026-09-27', 'pending-conversion');
        $this->forceAccepted($pending);
        DB::table('finance_quote_drafts')->insert([
            'document_series_id' => $this->seriesId($pending), 'user_id' => $owner->id,
            'based_on_revision_id' => $pending->currentRevision->id,
            'payload' => json_encode($pending->currentRevision->snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $pending->netMinor, 'vat_minor' => $pending->vatMinor,
            'gross_minor' => $pending->grossMinor, 'currency' => 'EUR',
            'updated_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectQuoteError('quote_draft_pending', fn () => $this->convertCommand($port)->handle(
            new ConvertQuoteToInvoiceData($pending->id, 1, $pending->currentRevision->id, 'convert-pending'),
        ));

        [$replaced, $oldRevisionId] = $this->quoteWithReplacedRevision($owner);
        $this->forceAccepted($replaced);
        $this->expectQuoteError('quote_revision_replaced', fn () => $this->convertCommand($port)->handle(
            new ConvertQuoteToInvoiceData($replaced->id, 1, $oldRevisionId, 'convert-replaced'),
        ));

        $this->assertSame(0, $port->calls);
        $this->assertSame(0, DB::table('finance_quote_conversions')->count());
    }

    public function test_conversion_failure_rolls_back_and_same_key_resumes_exactly_once(): void
    {
        $owner = User::factory()->create();
        $quote = $this->publishedQuote($owner, '2026-09-27');
        $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
            $quote->id, 0, $quote->currentRevision->id, 'accept-for-recovery',
        ));
        $data = new ConvertQuoteToInvoiceData($quote->id, 1, $quote->currentRevision->id, 'recover-conversion');

        try {
            $this->convertCommand(new ThrowingQuoteToInvoicePort)->handle($data);
            $this->fail('Injected target failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected target failure', $exception->getMessage());
        }
        $this->assertSame('accepted', $this->repository()->get($quote->id)->status);
        $this->assertSame(0, DB::table('finance_quote_conversions')->count());
        $this->assertSame(0, $this->activityCount($quote, 'quote.converted'));

        $recovery = new RecordingQuoteToInvoicePort;
        $target = $this->convertCommand($recovery)->handle($data);
        $this->assertSame('test-invoice:'.$owner->id, $target->targetReference);
        $this->assertSame(1, $recovery->calls);
        $this->assertSame('converted', $this->repository()->get($quote->id)->status);
    }

    public function test_conversion_database_rejects_a_foreign_owner_target_and_rolls_back(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignInvoice = new Invoice;
        $foreignInvoice->forceFill([
            'user_id' => $otherOwner->id, 'status' => 'draft', 'type' => 'invoice',
            'currency' => 'EUR', 'issue_date' => '2026-09-27',
        ])->save();
        $quote = $this->publishedQuote($owner, '2026-09-27');
        $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
            $quote->id, 0, $quote->currentRevision->id, 'accept-foreign-target',
        ));

        try {
            $this->convertCommand(new FixedQuoteToInvoicePort(new InvoiceDraftTarget(
                'legacy-invoice:'.$foreignInvoice->id,
                (int) $foreignInvoice->id,
            )))->handle(new ConvertQuoteToInvoiceData(
                $quote->id, 1, $quote->currentRevision->id, 'foreign-target',
            ));
            $this->fail('A foreign owner invoice target must violate the owner-safe conversion foreign key.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('accepted', $this->repository()->get($quote->id)->status);
        $this->assertSame(0, DB::table('finance_quote_conversions')->count());
    }

    public function test_action_keys_are_input_bound_and_owner_scope_is_hidden(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $quote = $this->publishedQuote($owner, '2026-09-27');
        $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
            $quote->id, 0, $quote->currentRevision->id, 'bound-key',
        ));

        try {
            $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
                $quote->id, 1, $quote->currentRevision->id, 'bound-key',
            ));
            $this->fail('Changed input must not reuse an idempotency key.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        $this->assertModelNotFound(fn () => $this->app->make(DuplicateQuote::class)->handle(
            new DuplicateQuoteData(
                new QuoteId((int) $otherOwner->id, $quote->id->uuid),
                0,
                $quote->currentRevision->id,
                'foreign-owner',
            ),
        ));
    }

    public function test_legacy_adapter_maps_snapshot_to_editor_reporting_print_and_stock_contracts(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->forceFill([
            'timezone' => 'Europe/Berlin',
            'invoice_payment_terms_days' => 21,
        ])->save();

        $product = new FinanceProduct;
        $product->fill([
            'kind' => 'hardware',
            'sku' => 'SW-24',
            'name' => 'Managed Switch',
            'unit' => 'piece',
            'price_net' => '100.00',
            'vat_rate' => '19.00',
            'track_stock' => true,
        ]);
        $product->save();
        StockLedger::move($product, '10.0000', 'purchase');

        $draft = $this->draft(
            issueDate: '2026-08-28',
            validUntil: '2026-09-27',
            lines: [new QuoteLineData(
                'Managed Switch', '2.5000', 'piece', '100.00', '19.00', 'hardware', (int) $product->id,
            )],
            discountType: 'fixed',
            discountValue: '20.00',
        );
        $quote = $this->publishedQuote($owner, '2026-09-27', 'legacy-contract', $draft);
        $this->app->make(AcceptQuote::class)->handle(new DecideQuoteData(
            $quote->id, 0, $quote->currentRevision->id, 'accept-legacy-contract',
        ));
        $adapter = $this->app->make(LegacyInvoiceDraftAdapter::class);

        $target = $this->convertCommand($adapter)->handle(new ConvertQuoteToInvoiceData(
            $quote->id, 1, $quote->currentRevision->id, 'convert-legacy-contract',
        ));
        $invoice = Invoice::query()->withoutGlobalScope('owner')->findOrFail($target->targetId);

        $this->assertSame('legacy-invoice:'.$invoice->id, $target->targetReference);
        $this->assertSame((int) $owner->id, (int) $invoice->user_id);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('invoice', $invoice->type);
        $this->assertNull($invoice->number);
        $this->assertSame('2026-09-27', $invoice->issue_date?->format('Y-m-d'));
        $this->assertSame('2026-10-18', $invoice->due_date?->format('Y-m-d'));
        $this->assertSame('230.00', $invoice->net);
        $this->assertSame('43.70', $invoice->vat);
        $this->assertSame('273.70', $invoice->gross);
        $this->assertSame($quote->currentRevision->snapshot['customer'], $invoice->customer);
        $this->assertSame([[
            'desc' => 'Managed Switch',
            'qty' => '2.5000',
            'unit' => 'piece',
            'unitPrice' => '100.00',
            'vatRate' => '19.00',
            'kind' => 'hardware',
            'productId' => (int) $product->id,
        ]], $invoice->lines);
        $this->assertSame('amount', $invoice->discount_type);
        $this->assertSame('20.00', $invoice->discount_value);

        $reported = $this->app->make(FinanceReports::class)->invoiceTotals($invoice);
        $this->assertEqualsWithDelta(230.00, $reported['net'], 0.001);
        $this->assertEqualsWithDelta(43.70, $reported['vat'], 0.001);
        $this->assertEqualsWithDelta(273.70, $reported['gross'], 0.001);

        $this->getJson(route('api.finance.data'))
            ->assertOk()
            ->assertJsonPath('invoices.0.lines.0.desc', 'Managed Switch')
            ->assertJsonPath('invoices.0.lines.0.qty', '2.5000')
            ->assertJsonPath('invoices.0.lines.0.unitPrice', '100.00')
            ->assertJsonPath('invoices.0.lines.0.vatRate', '19.00')
            ->assertJsonPath('invoices.0.lines.0.productId', (int) $product->id)
            ->assertJsonMissingPath('invoices.0.lines.0.description')
            ->assertJsonMissingPath('invoices.0.lines.0.unit_price');

        $this->assertSame(1, FinanceStockMovement::query()->count());
        // Finalizing this legacy-adapter invoice and its stock booking used to be
        // asserted here against the now-deleted legacy FinanceController::
        // finalizeInvoice route (bookGoodsOut()). That specific mechanism no longer
        // exists for ANY invoice after the Task 17 cutover; hardware stock booking
        // on finalize is covered equivalently -- and more thoroughly -- against the
        // finance-v2 invoice module by tests/Feature/FinanceModule/
        // InvoiceFinalizationTest.php::
        // test_inventory_sale_is_scale_four_owner_scoped_hardware_only_and_idempotent().
        // This test's own purpose (LegacyInvoiceDraftAdapter's snapshot -> legacy
        // shape mapping) is fully covered above.
    }

    public function test_legacy_adapter_maps_no_discount_to_legacy_nulls(): void
    {
        $owner = User::factory()->create();
        $quote = $this->publishedQuote(
            $owner,
            '2026-09-27',
            'legacy-no-discount',
            $this->draft(
                issueDate: '2026-08-28',
                validUntil: '2026-09-27',
                discountType: 'none',
                discountValue: null,
            ),
        );

        $target = $this->app->make(LegacyInvoiceDraftAdapter::class)->createDraft(
            (int) $owner->id,
            $quote->currentRevision,
            $quote->currentRevision->snapshot,
        );
        $invoice = Invoice::query()->withoutGlobalScope('owner')->findOrFail($target->targetId);

        $this->assertNull($invoice->discount_type);
        $this->assertNull($invoice->discount_value);
    }

    public function test_legacy_adapter_rejects_foreign_partner_references(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignPartnerId = (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign partner',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $quote = $this->publishedQuote($owner, '2026-09-27');
        $snapshot = [...$quote->currentRevision->snapshot, 'partner_id' => $foreignPartnerId];
        $source = new QuoteRevisionRef(
            $quote->currentRevision->id,
            $quote->currentRevision->revisionNumber,
            $quote->currentRevision->previousRevisionId,
            $quote->currentRevision->status,
            $snapshot,
            $quote->currentRevision->netMinor,
            $quote->currentRevision->vatMinor,
            $quote->currentRevision->grossMinor,
            $quote->currentRevision->currency,
            $quote->currentRevision->pdfPath,
            $quote->currentRevision->pdfSha256,
            $quote->currentRevision->publishedAt,
            $quote->currentRevision->createdAt,
        );

        $this->assertModelNotFound(fn () => $this->app->make(LegacyInvoiceDraftAdapter::class)
            ->createDraft((int) $owner->id, $source, $snapshot));
        $this->assertSame(0, Invoice::query()->withoutGlobalScope('owner')->count());
    }

    public function test_postgresql_concurrent_different_keys_create_one_invoice_target(): void
    {
        $this->withIsolatedPostgresSchema(function (string $postgresUrl, string $schema): void {
            $source = $this->storePostgresAcceptedQuote();
            $command = new ConvertQuoteToInvoice(
                $this->repository(),
                $this->app->make(QuoteOperationRepository::class),
                $this->app->make(LegacyInvoiceDraftAdapter::class),
            );
            DB::beginTransaction();
            $process = null;
            try {
                $parentTarget = $command->handle(new ConvertQuoteToInvoiceData(
                    new QuoteId(1, $source['uuid']),
                    0,
                    $source['revision_id'],
                    'postgres-parent-conversion',
                ));
                $process = $this->startPostgresConversionWorker(
                    $postgresUrl,
                    $schema,
                    $source['uuid'],
                    $source['revision_id'],
                );
                $pid = $this->waitForWorkerPid($process);
                $this->waitForPostgresLock($pid);
                $this->assertTrue($process->isRunning(), 'The competing conversion did not wait on the quote lock.');
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
            $workerTarget = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($parentTarget->targetReference, $workerTarget['target_reference'] ?? null);
            $this->assertSame(1, DB::table('invoices')->count());
            $this->assertSame(1, DB::table('finance_quote_conversions')->count());
            $this->assertSame(2, DB::table('finance_quote_operations')
                ->where('operation', 'convert_invoice')->where('state', 'succeeded')->count());
        });
    }

    private function convertCommand(QuoteToInvoicePort $port): ConvertQuoteToInvoice
    {
        return new ConvertQuoteToInvoice(
            $this->repository(),
            $this->app->make(QuoteOperationRepository::class),
            $port,
        );
    }

    private function publishedQuote(
        User $owner,
        string $validUntil,
        string $suffix = 'quote',
        ?QuoteDraftData $draft = null,
    ): QuoteView {
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-'.$suffix.'-'.$owner->id,
            $draft ?? $this->draft(issueDate: '2026-08-28', validUntil: $validUntil),
        );
        $seriesId = $this->seriesId($created);
        $snapshot = [
            ...$created->draft,
            'schema_version' => 1,
            'document_type' => 'quote',
            'series_uuid' => $created->id->uuid,
            'document_number' => 'AN-2026-'.$seriesId,
            'revision_number' => 1,
            'revision_label' => 'AN-2026-'.$seriesId.'-V1',
        ];
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 1,
            'previous_revision_id' => null, 'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $created->netMinor, 'vat_minor' => $created->vatMinor,
            'gross_minor' => $created->grossMinor, 'currency' => $created->currency,
            'change_reason' => 'Initial', 'pdf_path' => 'finance/quotes/'.$seriesId.'.pdf',
            'pdf_sha256' => hash('sha256', 'pdf-'.$seriesId), 'published_at' => $this->clock->now(),
            'created_by' => $owner->id, 'created_at' => $this->clock->now(),
        ]);
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->delete();
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId, 'number' => 'AN-2026-'.$seriesId,
            'sequence_year' => 2026, 'sequence_number' => $seriesId,
            'published_at' => $this->clock->now(),
        ]);
        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => 'sent']);

        return $this->repository()->get($created->id);
    }

    /** @return array{QuoteView, int} */
    private function quoteWithReplacedRevision(User $owner): array
    {
        $quote = $this->publishedQuote($owner, '2026-09-27', 'replaced');
        $oldId = $quote->currentRevision->id;
        $seriesId = $this->seriesId($quote);
        $snapshot = [...$quote->currentRevision->snapshot, 'revision_number' => 2, 'revision_label' => $quote->number.'-V2'];
        $newId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 2,
            'previous_revision_id' => $oldId, 'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $quote->netMinor, 'vat_minor' => $quote->vatMinor,
            'gross_minor' => $quote->grossMinor, 'currency' => $quote->currency,
            'change_reason' => 'Revision', 'pdf_path' => 'finance/quotes/'.$seriesId.'-2.pdf',
            'pdf_sha256' => hash('sha256', 'pdf-'.$seriesId.'-2'), 'published_at' => $this->clock->now(),
            'created_by' => $owner->id, 'created_at' => $this->clock->now(),
        ]);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update(['current_revision_id' => $newId]);

        return [$this->repository()->get($quote->id), $oldId];
    }

    /** @param list<QuoteLineData>|null $lines */
    private function draft(
        string $issueDate,
        string $validUntil,
        ?array $lines = null,
        string $discountType = 'percent',
        ?string $discountValue = '10.00',
        ?int $partnerId = null,
    ): QuoteDraftData {
        return new QuoteDraftData(
            'Network refresh', $partnerId, ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            $issueDate, $validUntil, 'EUR', $lines ?? [new QuoteLineData(
                'Consulting', '2.5000', 'hour', '100.00', '19.00', 'service', null,
            )], $discountType, $discountValue, 'Intro', 'Outro', 'Internal only',
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

    private function repository(): QuoteRepository
    {
        return $this->app->make(QuoteRepository::class);
    }

    private function seriesId(QuoteView $quote): int
    {
        return (int) DB::table('finance_document_series')
            ->where('user_id', $quote->id->ownerId)->where('uuid', $quote->id->uuid)->value('id');
    }

    private function activityCount(QuoteView $quote, string $type): int
    {
        return DB::table('finance_document_activities')
            ->where('document_series_id', $this->seriesId($quote))->where('type', $type)->count();
    }

    private function forceAccepted(QuoteView $quote): void
    {
        DB::table('finance_quote_series')->where('document_series_id', $this->seriesId($quote))->update([
            'version' => 1,
            'accepted_at' => $this->clock->now(),
        ]);
        DB::table('finance_document_series')->where('id', $this->seriesId($quote))->update([
            'status' => 'accepted',
        ]);
    }

    private function expectQuoteError(string $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected quote action error '.$code.'.');
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }

    private function assertModelNotFound(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected an owner-scoped model-not-found result.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run Quote conversion concurrency tests.',
            );
        }
        $postgresConfig = config('database.connections.pgsql');
        if (! is_array($postgresConfig)) {
            throw new RuntimeException('PostgreSQL connection configuration is unavailable.');
        }
        $defaultConnection = DB::getDefaultConnection();
        $connectionName = 'pgsql_quote_conversion_concurrency';
        $schema = 'finance_quote_task9_'.bin2hex(random_bytes(8));
        config(["database.connections.{$connectionName}" => array_merge(
            $postgresConfig,
            ['url' => $postgresUrl, 'search_path' => 'public'],
        )]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $created = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $created = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($connectionName);
            Schema::clearResolvedInstance('db.schema');
            Schema::create('users', static function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('finance_partners', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
            Schema::create('invoices', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('number')->nullable();
                $table->unsignedInteger('seq')->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('status', 16)->default('draft');
                $table->string('type', 16)->default('invoice');
                $table->date('issue_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('currency', 8)->default('EUR');
                $table->foreignId('partner_id')->nullable();
                $table->longText('customer')->nullable();
                $table->longText('lines')->nullable();
                $table->string('discount_type', 16)->nullable();
                $table->decimal('discount_value', 12, 2)->nullable();
                $table->decimal('net', 14, 2)->nullable();
                $table->decimal('vat', 14, 2)->nullable();
                $table->decimal('gross', 14, 2)->nullable();
                $table->text('note')->nullable();
                $table->boolean('imported')->default(false);
                $table->unsignedInteger('version')->default(0);
                $table->unsignedInteger('version_seq')->default(0);
                $table->string('pdf_path')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
            Schema::create('user_settings', static function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
                $table->string('timezone')->nullable();
                $table->unsignedInteger('invoice_payment_terms_days')->nullable();
                $table->timestamps();
            });
            foreach ([
                '2026_08_28_100000_create_finance_document_core.php',
                '2027_03_03_100000_create_finance_quote_workflow.php',
            ] as $migrationFile) {
                $migration = require database_path('migrations/'.$migrationFile);
                if (! is_object($migration) || ! is_callable([$migration, 'up'])) {
                    throw new RuntimeException("Finance migration {$migrationFile} is unavailable.");
                }
                $migration->up();
            }
            DB::table('users')->insert(['id' => 1]);
            DB::table('user_settings')->insert([
                'user_id' => 1,
                'timezone' => 'UTC',
                'invoice_payment_terms_days' => 14,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $test($postgresUrl, $schema);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');
            try {
                if ($created) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($connectionName);
            }
        }
    }

    /** @return array{uuid: string, revision_id: int} */
    private function storePostgresAcceptedQuote(): array
    {
        $uuid = '018f4ca3-224d-7d8d-9f09-000000000001';
        $now = '2026-08-29 10:00:00';
        $snapshot = [
            'schema_version' => 1, 'document_type' => 'quote', 'series_uuid' => $uuid,
            'document_number' => 'AN-2026-0001', 'revision_number' => 1,
            'revision_label' => 'AN-2026-0001', 'title' => 'Concurrent quote',
            'customer' => ['name' => 'Ada GmbH'], 'partner_id' => null,
            'issue_date' => '2026-08-29', 'valid_until' => '2099-12-31', 'currency' => 'EUR',
            'lines' => [[
                'description' => 'Consulting', 'quantity' => '1.0000', 'quantity_scaled' => 10_000,
                'unit' => 'hour', 'unit_price' => '100.00', 'unit_price_minor' => 10_000,
                'currency' => 'EUR', 'tax_rate' => '19.00', 'tax_rate_basis_points' => 1900,
                'kind' => 'service', 'product_id' => null,
            ]],
            'discount' => ['type' => 'none', 'value' => null, 'currency' => 'EUR'],
            'totals' => [
                'net_minor' => 10_000, 'vat_minor' => 1_900, 'gross_minor' => 11_900,
                'discount_minor' => 0, 'currency' => 'EUR', 'tax_breakdowns' => [],
            ],
            'intro_text' => null, 'outro_text' => null, 'customer_note' => null,
        ];
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => 1, 'uuid' => $uuid, 'document_type' => 'quote', 'status' => 'accepted',
            'source_type' => null, 'source_id' => null, 'created_by' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => 1, 'document_series_id' => $seriesId, 'revision_number' => 1,
            'previous_revision_id' => null, 'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => 10_000, 'vat_minor' => 1_900, 'gross_minor' => 11_900,
            'currency' => 'EUR', 'change_reason' => null, 'pdf_path' => 'quotes/concurrent.pdf',
            'pdf_sha256' => hash('sha256', 'concurrent'), 'published_at' => $now,
            'created_by' => 1, 'created_at' => $now,
        ]);
        DB::table('finance_quote_series')->insert([
            'document_series_id' => $seriesId, 'user_id' => 1, 'document_type' => 'quote',
            'partner_id' => null, 'current_revision_id' => $revisionId,
            'number' => 'AN-2026-0001', 'sequence_year' => 2026, 'sequence_number' => 1,
            'version' => 0, 'published_at' => $now, 'accepted_at' => $now,
            'declined_at' => null, 'converted_at' => null, 'deleted_at' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['uuid' => $uuid, 'revision_id' => $revisionId];
    }

    private function startPostgresConversionWorker(
        string $postgresUrl,
        string $schema,
        string $quoteUuid,
        int $revisionId,
    ): Process {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $uuid = getenv('FINANCE_TEST_QUOTE_UUID');
            $revision = getenv('FINANCE_TEST_QUOTE_REVISION');
            if (! is_string($url) || ! is_string($schema) || ! is_string($uuid)
                || ! is_string($revision) || ! ctype_digit($revision)
                || preg_match('/\Afinance_quote_task9_[0-9a-f]{16}\z/D', $schema) !== 1) {
                fwrite(STDERR, 'invalid-postgres-worker-configuration'); exit(90);
            }
            $name = 'pgsql_quote_conversion_worker';
            $base = config('database.connections.pgsql');
            config(["database.connections.{$name}" => array_merge(is_array($base) ? $base : [], [
                'driver' => 'pgsql', 'url' => $url, 'search_path' => $schema,
            ])]);
            \Illuminate\Support\Facades\DB::purge($name);
            $connection = \Illuminate\Support\Facades\DB::connection($name);
            $connection->statement('SET search_path TO "'.$schema.'"');
            \Illuminate\Support\Facades\DB::setDefaultConnection($name);
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
            echo $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid.PHP_EOL;
            flush();
            $command = new \App\Modules\Finance\Application\Commands\Quotes\ConvertQuoteToInvoice(
                $app->make(\App\Modules\Finance\Application\Ports\Quotes\QuoteRepository::class),
                $app->make(\App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository::class),
                $app->make(\App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDraftAdapter::class),
            );
            try {
                $target = $command->handle(new \App\Modules\Finance\Application\DTOs\Quotes\ConvertQuoteToInvoiceData(
                    new \App\Modules\Finance\Application\DTOs\Quotes\QuoteId(1, $uuid),
                    0,
                    (int) $revision,
                    'postgres-worker-conversion',
                ));
                echo json_encode(['target_reference' => $target->targetReference], JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                fwrite(STDERR, $exception::class.':'.$exception->getMessage()); exit(91);
            }
            PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'FINANCE_TEST_PGSQL_URL' => $postgresUrl,
            'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
            'FINANCE_TEST_QUOTE_UUID' => $quoteUuid,
            'FINANCE_TEST_QUOTE_REVISION' => (string) $revisionId,
        ], null, 20);
        $process->start();

        return $process;
    }

    private function waitForWorkerPid(Process $process): int
    {
        $deadline = microtime(true) + 5.0;
        do {
            $first = strtok($process->getOutput(), "\r\n");
            if (is_string($first) && ctype_digit(trim($first))) {
                return (int) trim($first);
            }
            usleep(20_000);
        } while ($process->isRunning() && microtime(true) < $deadline);

        throw new RuntimeException('PostgreSQL conversion worker did not expose its backend PID.'.$process->getErrorOutput());
    }

    private function waitForPostgresLock(int $pid): void
    {
        $deadline = microtime(true) + 5.0;
        do {
            $activity = DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [$pid]);
            if (is_object($activity) && ($activity->wait_event_type ?? null) === 'Lock') {
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('PostgreSQL conversion worker did not wait on the aggregate lock.');
    }
}

final class RecordingQuoteToInvoicePort implements QuoteToInvoicePort
{
    public int $calls = 0;

    public ?QuoteRevisionRef $source = null;

    /** @var array<array-key, mixed>|null */
    public ?array $snapshot = null;

    public function createDraft(int $ownerId, QuoteRevisionRef $source, array $immutableSnapshot): InvoiceDraftTarget
    {
        $this->calls++;
        $this->source = $source;
        $this->snapshot = $immutableSnapshot;

        return new InvoiceDraftTarget('test-invoice:'.$ownerId, null);
    }
}

final class QuoteDecisionClock implements Clock
{
    public function __construct(public DateTimeImmutable $value) {}

    public function now(): DateTimeImmutable
    {
        return $this->value;
    }
}

final class ThrowingQuoteToInvoicePort implements QuoteToInvoicePort
{
    public function createDraft(int $ownerId, QuoteRevisionRef $source, array $immutableSnapshot): InvoiceDraftTarget
    {
        throw new RuntimeException('injected target failure');
    }
}

final readonly class FixedQuoteToInvoicePort implements QuoteToInvoicePort
{
    public function __construct(private InvoiceDraftTarget $target) {}

    public function createDraft(int $ownerId, QuoteRevisionRef $source, array $immutableSnapshot): InvoiceDraftTarget
    {
        return $this->target;
    }
}
