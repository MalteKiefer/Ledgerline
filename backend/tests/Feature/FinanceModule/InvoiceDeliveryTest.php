<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Invoices\QueueInvoiceDelivery;
use App\Modules\Finance\Application\Commands\Invoices\RetryInvoiceDelivery;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Infrastructure\Mail\CompanyInvoiceMailer;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransport;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransportResult;
use App\Modules\Finance\Infrastructure\Mail\CompanySmtpMailer;
use App\Modules\Finance\Infrastructure\Mail\InvoiceRevisionMail;
use App\Modules\Finance\Infrastructure\Mail\SafePreAcceptMailFailure;
use App\Modules\Finance\Infrastructure\Mail\UncertainMailTransportFailure;
use App\Modules\Finance\Infrastructure\Scheduling\SendInvoiceDeliveryJob;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InvoiceDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_invoice_queues_one_idempotent_delivery_after_commit(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId, $revisionId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $mailer = new RecordingInvoiceMailer;
        app()->instance(InvoiceMailer::class, $mailer);
        $command = app(QueueInvoiceDelivery::class);

        $first = $command->handle($invoiceId, 'customer@example.test', new IdempotencyKey('send-invoice-1'));
        $second = $command->handle($invoiceId, 'customer@example.test', new IdempotencyKey('send-invoice-1'));

        $this->assertSame($first->value, $second->value);
        $this->assertIsString($first->uuid);
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $first->uuid,
        );
        $this->assertDatabaseCount('finance_invoice_deliveries', 1);
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $first->value,
            'user_id' => $owner->id,
            'invoice_id' => $invoiceId->value,
            'document_revision_id' => $revisionId,
            'kind' => 'invoice',
            'recipient' => 'customer@example.test',
            'status' => 'pending',
        ]);
        $this->assertCount(1, $mailer->dispatched);
        $this->assertSame([(int) $owner->id, $first->value], $mailer->dispatched[0]);
        $serialized = serialize(new SendInvoiceDeliveryJob((int) $owner->id, $first->value));
        $this->assertStringNotContainsString('customer@example.test', $serialized);
        $this->assertStringNotContainsString('finance/revisions/', $serialized);
    }

    public function test_same_key_with_different_recipient_conflicts_without_dispatching_again(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $mailer = new RecordingInvoiceMailer;
        app()->instance(InvoiceMailer::class, $mailer);
        $command = app(QueueInvoiceDelivery::class);
        $command->handle($invoiceId, 'first@example.test', new IdempotencyKey('same-send-key'));

        try {
            $command->handle($invoiceId, 'second@example.test', new IdempotencyKey('same-send-key'));
            $this->fail('A reused key with a different delivery payload was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('delivery_idempotency_conflict', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_invoice_deliveries', 1);
        $this->assertCount(1, $mailer->dispatched);
    }

    public function test_delivery_lookup_is_owner_scoped_and_does_not_reveal_foreign_invoice_state(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [, $invoiceId] = $this->finalizedInvoice();
        $otherOwner = User::factory()->create();
        $this->actingAs($otherOwner);
        app()->instance(InvoiceMailer::class, new RecordingInvoiceMailer);

        $this->expectException(ModelNotFoundException::class);
        try {
            app(QueueInvoiceDelivery::class)->handle(
                $invoiceId,
                'customer@example.test',
                new IdempotencyKey('foreign-delivery'),
            );
        } finally {
            $this->assertDatabaseCount('finance_invoice_deliveries', 0);
        }
    }

    public function test_production_adapter_dispatches_only_the_delivery_identity_after_commit(): void
    {
        Queue::fake();
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        app()->instance(InvoiceMailer::class, new CompanyInvoiceMailer(app(CompanySmtpMailer::class)));

        $delivery = app(QueueInvoiceDelivery::class)->handle(
            $invoiceId,
            null,
            new IdempotencyKey('after-commit-dispatch'),
        );

        Queue::assertPushed(SendInvoiceDeliveryJob::class, static fn (SendInvoiceDeliveryJob $job): bool => $job->ownerId === (int) $owner->id
            && $job->deliveryId === $delivery->value
            && $job->afterCommit === true);
    }

    public function test_missing_or_digest_mismatched_pdf_bytes_fail_before_delivery_dispatch(): void
    {
        Queue::fake();
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId, $revisionId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        app()->instance(InvoiceMailer::class, new CompanyInvoiceMailer(app(CompanySmtpMailer::class)));
        $path = DB::table('finance_document_revisions')->where('id', $revisionId)->value('pdf_path');
        $this->assertIsString($path);
        Storage::disk('invoice-delivery-pdfs')->delete($path);

        try {
            app(QueueInvoiceDelivery::class)->handle(
                $invoiceId,
                null,
                new IdempotencyKey('missing-pdf-object'),
            );
            $this->fail('A delivery without immutable PDF bytes was queued.');
        } catch (DomainException $exception) {
            $this->assertSame('delivery_pdf_unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('finance_invoice_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_ineligible_invoice_missing_recipient_pdf_or_smtp_fails_before_queue_dispatch(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId, $revisionId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $mailer = new RecordingInvoiceMailer;
        app()->instance(InvoiceMailer::class, $mailer);
        $command = app(QueueInvoiceDelivery::class);

        DB::table('finance_invoices')->where('id', $invoiceId->value)->update(['workflow_status' => 'draft']);
        $this->assertDeliveryError($command, $invoiceId, 'customer@example.test', 'draft-key', 'delivery_invoice_not_eligible');
        DB::table('finance_invoices')->where('id', $invoiceId->value)->update(['workflow_status' => 'finalized']);
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'snapshot' => json_encode(['customer' => []], JSON_THROW_ON_ERROR),
        ]);
        $this->assertDeliveryError($command, $invoiceId, null, 'recipient-key', 'delivery_recipient_missing');
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_path' => null,
            'pdf_sha256' => null,
        ]);
        $this->assertDeliveryError($command, $invoiceId, 'customer@example.test', 'pdf-key', 'delivery_pdf_unavailable');
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'pdf_path' => 'finance/revisions/aa/'.str_repeat('a', 64).'.pdf',
            'pdf_sha256' => str_repeat('a', 64),
        ]);
        $mailer->configured = false;
        $this->assertDeliveryError($command, $invoiceId, 'customer@example.test', 'smtp-key', 'delivery_smtp_unavailable');

        $this->assertDatabaseCount('finance_invoice_deliveries', 0);
        $this->assertSame([], $mailer->dispatched);
    }

    public function test_worker_sends_digest_verified_pdf_with_stable_message_id_and_transitions_once(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        app()->instance(InvoiceMailer::class, new RecordingInvoiceMailer);
        $delivery = app(QueueInvoiceDelivery::class)->handle(
            $invoiceId,
            null,
            new IdempotencyKey('worker-success'),
        );
        $transport = new RecordingCompanyTransport;

        (new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));

        $row = DB::table('finance_invoice_deliveries')->find($delivery->value);
        $this->assertNotNull($row);
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertSame($row->message_id, $transport->mail?->messageId);
        $this->assertSame(hash('sha256', '%PDF-invoice-delivery'), hash('sha256', $transport->mail?->pdfBytes ?? ''));
        $this->assertDatabaseHas('finance_invoices', [
            'id' => $invoiceId->value,
            'workflow_status' => 'sent',
        ]);
        $activity = DB::table('finance_document_activities')->where('type', 'invoice.sent')->first();
        $this->assertNotNull($activity);
        $this->assertStringNotContainsString('customer@example.test', (string) $activity->payload);
        (new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));
        $this->assertSame(1, $transport->calls);
        $this->assertSame([60, 300, 1800], (new SendInvoiceDeliveryJob(1, 1))->backoff());
    }

    public function test_safe_failures_retry_but_uncertain_outcomes_stop_automatic_delivery(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        app()->instance(InvoiceMailer::class, new RecordingInvoiceMailer);
        $delivery = app(QueueInvoiceDelivery::class)->handle(
            $invoiceId,
            null,
            new IdempotencyKey('worker-failure'),
        );
        $safe = new RecordingCompanyTransport(new SafePreAcceptMailFailure('secret recipient detail'));

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $job = new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value);
            $fakeJob = new FakeJob;
            $fakeJob->attempts = $attempt;
            $job->setJob($fakeJob);
            try {
                $job->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($safe)));
            } catch (\RuntimeException $exception) {
                $this->assertLessThan(3, $attempt);
                $this->assertSame('invoice_delivery_failed', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $delivery->value,
            'status' => 'failed',
            'attempts' => 3,
            'last_error_code' => 'smtp_send_failed',
        ]);
        $this->assertDatabaseHas('finance_invoices', [
            'id' => $invoiceId->value,
            'workflow_status' => 'finalized',
        ]);

        DB::table('finance_invoice_deliveries')->where('id', $delivery->value)->update([
            'status' => 'pending', 'attempts' => 0, 'last_error_code' => null,
        ]);
        $uncertain = new RecordingCompanyTransport(new UncertainMailTransportFailure('accepted maybe'));
        $job = new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value);
        $job->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($uncertain)));
        $job->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($uncertain)));
        $this->assertSame(1, $uncertain->calls);
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $delivery->value,
            'status' => 'unknown',
            'attempts' => 1,
            'last_error_code' => 'delivery_outcome_uncertain',
        ]);
    }

    public function test_explicit_retry_creates_one_new_bounded_delivery_for_the_same_revision_and_pdf(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId, $revisionId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $dispatch = new RecordingInvoiceMailer;
        app()->instance(InvoiceMailer::class, $dispatch);
        $first = app(QueueInvoiceDelivery::class)->handle(
            $invoiceId,
            null,
            new IdempotencyKey('retry-original'),
        );
        DB::table('finance_invoice_deliveries')->where('id', $first->value)->update([
            'status' => 'unknown',
            'attempts' => 1,
            'last_error_code' => 'delivery_outcome_uncertain',
        ]);

        $retry = app(RetryInvoiceDelivery::class)->handle(
            $first,
            new IdempotencyKey('retry-explicit-1'),
        );
        $replay = app(RetryInvoiceDelivery::class)->handle(
            $first,
            new IdempotencyKey('retry-explicit-1'),
        );

        $this->assertNotSame($first->value, $retry->value);
        $this->assertSame($retry->value, $replay->value);
        $this->assertDatabaseCount('finance_invoice_deliveries', 2);
        $retried = DB::table('finance_invoice_deliveries')->find($retry->value);
        $original = DB::table('finance_invoice_deliveries')->find($first->value);
        $this->assertNotNull($retried);
        $this->assertNotNull($original);
        $this->assertSame($revisionId, $retried->document_revision_id);
        $this->assertSame($original->recipient, $retried->recipient);
        $this->assertSame('pending', $retried->status);
        $this->assertSame(0, $retried->attempts);
        $this->assertNotSame($original->uuid, $retried->uuid);
        $this->assertCount(2, $dispatch->dispatched);
    }

    public function test_persistence_failure_after_transport_acceptance_becomes_unknown_before_any_resend(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        app()->instance(InvoiceMailer::class, new RecordingInvoiceMailer);
        $delivery = app(QueueInvoiceDelivery::class)->handle(
            $invoiceId,
            null,
            new IdempotencyKey('post-acceptance-crash'),
        );
        $transport = new RecordingCompanyTransport;
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER invoice_delivery_sent_crash
            BEFORE UPDATE OF status ON finance_invoice_deliveries
            WHEN NEW.status = 'sent'
            BEGIN
                SELECT RAISE(ABORT, 'injected post-send crash');
            END
            SQL);

        try {
            (new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value))
                ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));
            $this->fail('The injected delivery persistence failure did not occur.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('injected post-send crash', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER invoice_delivery_sent_crash');
        }
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $delivery->value,
            'status' => 'sending',
            'attempts' => 1,
        ]);

        (new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));

        $this->assertSame(1, $transport->calls);
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $delivery->value,
            'status' => 'unknown',
            'last_error_code' => 'delivery_outcome_uncertain',
        ]);
    }

    public function test_explicit_retry_revalidates_the_same_immutable_pdf_before_creating_a_new_attempt(): void
    {
        Storage::fake('invoice-delivery-pdfs');
        config()->set('files.disk', 'invoice-delivery-pdfs');
        [$owner, $invoiceId, $revisionId] = $this->finalizedInvoice();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        app()->instance(InvoiceMailer::class, new RecordingInvoiceMailer);
        $delivery = app(QueueInvoiceDelivery::class)->handle(
            $invoiceId,
            null,
            new IdempotencyKey('retry-pdf-original'),
        );
        DB::table('finance_invoice_deliveries')->where('id', $delivery->value)->update([
            'status' => 'unknown',
            'attempts' => 1,
            'last_error_code' => 'delivery_outcome_uncertain',
        ]);
        $path = DB::table('finance_document_revisions')->where('id', $revisionId)->value('pdf_path');
        $this->assertIsString($path);
        Storage::disk('invoice-delivery-pdfs')->delete($path);
        app()->instance(InvoiceMailer::class, new CompanyInvoiceMailer(app(CompanySmtpMailer::class)));

        try {
            app(RetryInvoiceDelivery::class)->handle(
                $delivery,
                new IdempotencyKey('retry-pdf-attempt'),
            );
            $this->fail('A retry without its immutable PDF was created.');
        } catch (DomainException $exception) {
            $this->assertSame('delivery_pdf_unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('finance_invoice_deliveries', 1);
    }

    private function assertDeliveryError(
        QueueInvoiceDelivery $command,
        InvoiceId $invoiceId,
        ?string $recipient,
        string $key,
        string $expected,
    ): void {
        try {
            $command->handle($invoiceId, $recipient, new IdempotencyKey($key));
            $this->fail('The invalid invoice delivery was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }
    }

    /** @return array{User, InvoiceId, int} */
    private function finalizedInvoice(): array
    {
        $owner = User::factory()->create();
        $now = now();
        $uuid = '018f4ca3-224d-7d8d-9f00-123456789abc';
        $bytes = '%PDF-invoice-delivery';
        $sha256 = hash('sha256', $bytes);
        $path = 'finance/revisions/'.substr($sha256, 0, 2).'/'.$sha256.'.pdf';
        $snapshot = [
            'schema_version' => 1,
            'document_type' => 'invoice',
            'series_uuid' => $uuid,
            'document_number' => 'RE-2026-0042',
            'customer' => ['name' => 'Customer', 'email' => 'customer@example.test'],
            'due_date' => '2026-08-20',
            'totals' => ['gross_minor' => 11_900, 'currency' => 'EUR'],
        ];
        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $uuid,
            'document_type' => 'invoice',
            'status' => 'finalized',
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => 10_000,
            'vat_minor' => 1_900,
            'gross_minor' => 11_900,
            'currency' => 'EUR',
            'pdf_path' => $path,
            'pdf_sha256' => $sha256,
            'published_at' => $now,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
        $invoicePk = DB::table('finance_invoices')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $uuid,
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => 'invoice',
            'number' => 'RE-2026-0042',
            'year' => 2026,
            'sequence' => 42,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-20',
            'workflow_status' => 'finalized',
            'finalized_at' => $now,
            'allocated_minor' => 0,
            'open_minor' => 11_900,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Storage::disk('invoice-delivery-pdfs')->put($path, $bytes);

        return [$owner, new InvoiceId($invoicePk), $revisionId];
    }

    private function configureSmtp(int $ownerId): void
    {
        UserSetting::for($ownerId)->update([
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.test',
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
            'company_smtp_from_address' => 'billing@example.test',
            'company_smtp_from_name' => 'Ledgerline GmbH',
        ]);
    }
}

final class RecordingCompanyTransport implements CompanyMailTransport
{
    public int $calls = 0;

    public ?InvoiceRevisionMail $mail = null;

    public function __construct(private readonly ?\Throwable $failure = null) {}

    public function send(string $mailerName, string $recipient, Mailable $mail): CompanyMailTransportResult
    {
        $this->calls++;
        $this->mail = $mail instanceof InvoiceRevisionMail ? $mail : null;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return CompanyMailTransportResult::accepted();
    }
}

final class RecordingInvoiceMailer implements InvoiceMailer
{
    public bool $configured = true;

    /** @var list<array{int, int}> */
    public array $dispatched = [];

    public function assertConfigured(int $ownerId): void
    {
        if (! $this->configured) {
            throw new DomainException('delivery_smtp_unavailable');
        }
    }

    public function dispatch(int $ownerId, DeliveryId $deliveryId): void
    {
        $this->dispatched[] = [$ownerId, $deliveryId->value];
    }

    public function assertDocumentReady(string $path, string $sha256): void {}
}
