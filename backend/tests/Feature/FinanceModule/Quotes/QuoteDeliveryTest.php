<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Quotes\CreateQuote;
use App\Modules\Finance\Application\Commands\Quotes\PublishQuote;
use App\Modules\Finance\Application\Commands\Quotes\SendQuote;
use App\Modules\Finance\Application\Commands\Quotes\StartQuoteVersion;
use App\Modules\Finance\Application\DTOs\Quotes\PublishQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\DTOs\Quotes\SendQuoteData;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\Quotes\QuoteMailer;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransport;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransportResult;
use App\Modules\Finance\Infrastructure\Mail\CompanySmtpMailer;
use App\Modules\Finance\Infrastructure\Mail\Jobs\DeliverQuoteRevision;
use App\Modules\Finance\Infrastructure\Mail\QuoteRevisionMail;
use App\Modules\Finance\Infrastructure\Mail\SafePreAcceptMailFailure;
use App\Modules\Finance\Infrastructure\Mail\UncertainMailTransportFailure;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

final class QuoteDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_publishes_before_it_queues_the_exact_immutable_revision_after_commit(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-delivery-quote', $this->draft());

        $sent = app(SendQuote::class)->handle(new SendQuoteData(
            $quote->id,
            $quote->version,
            'send-delivery-quote',
        ));

        $this->assertSame('sent', $sent->status);
        $this->assertNull($sent->draft);
        $this->assertNotNull($sent->currentRevision);
        $this->assertNotNull($sent->currentRevision->pdfPath);
        $this->assertNotNull($sent->currentRevision->pdfSha256);
        $delivery = $this->deliveryRow();
        $this->assertSame((int) $owner->id, $delivery->userId);
        $this->assertSame($sent->currentRevision->id, $delivery->documentRevisionId);
        $this->assertSame('billing@example.com', $delivery->recipient);
        $this->assertSame('example.com', $delivery->recipientDomain);
        $this->assertSame('queued', $delivery->state);
        $deliveryUuid = DB::table('finance_quote_deliveries')->value('uuid');
        $this->assertIsString($deliveryUuid);
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $deliveryUuid,
        );
        $this->assertSame('<'.$deliveryUuid.'@quotes.ledgerline>', $delivery->messageId);
        $this->assertDatabaseHas('finance_document_activities', [
            'user_id' => $owner->id,
            'type' => 'quote.mail.queued',
        ]);
        Queue::assertPushed(
            DeliverQuoteRevision::class,
            static fn (DeliverQuoteRevision $job): bool => $job->ownerId === (int) $owner->id
                && $job->deliveryId === $delivery->id
                && $job->afterCommit === true,
        );
        $serializedJob = serialize(new DeliverQuoteRevision((int) $owner->id, $delivery->id));
        $this->assertStringNotContainsString('billing@example.com', $serializedJob);
        $this->assertStringNotContainsString((string) $sent->currentRevision->pdfPath, $serializedJob);
    }

    public function test_dispatch_failure_resumes_the_same_operation_delivery_revision_and_pdf(): void
    {
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $mailer = new ResumableQuoteMailer;
        app()->instance(QuoteMailer::class, $mailer);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-resumable-send', $this->draft());
        $data = new SendQuoteData($quote->id, $quote->version, 'resumable-send');
        $command = app(SendQuote::class);

        try {
            $command->handle($data);
            $this->fail('The injected queue dispatch failure did not occur.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected dispatch failure.', $exception->getMessage());
        }
        $delivery = $this->deliveryRow();
        $revision = $this->revisionRow();
        $pdfPath = $revision->pdfPath;

        $replayed = $command->handle($data);

        $this->assertSame($revision->id, $replayed->currentRevision?->id);
        $this->assertSame($pdfPath, $replayed->currentRevision?->pdfPath);
        $this->assertSame(1, DB::table('finance_quote_deliveries')->count());
        $this->assertSame($delivery->id, $this->deliveryRow()->id);
        $this->assertSame(2, $mailer->dispatches);
        $this->assertDatabaseHas('finance_quote_operations', [
            'operation' => 'send',
            'idempotency_key' => 'resumable-send',
            'state' => 'succeeded',
        ]);
    }

    public function test_delivery_uuid_migration_round_trips_existing_stable_message_identity(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-uuid-migration', $this->draft());
        app(SendQuote::class)->handle(new SendQuoteData($quote->id, $quote->version, 'send-uuid-migration'));
        $storedBefore = DB::table('finance_quote_deliveries')->first();
        $this->assertNotNull($storedBefore);
        $messageId = $this->deliveryRow()->messageId;
        $migration = require database_path(
            'migrations/2027_03_04_120000_add_uuid_to_finance_quote_deliveries.php',
        );
        if (! is_object($migration)
            || ! is_callable([$migration, 'down'])
            || ! is_callable([$migration, 'up'])) {
            throw new RuntimeException('Quote delivery UUID migration is unavailable.');
        }

        $this->assertInvalidDeliveryStateIsRejected();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('finance_quote_deliveries', 'uuid'));
        $this->assertSame($messageId, DB::table('finance_quote_deliveries')->value('message_id'));
        $this->assertSame($storedBefore->recipient, DB::table('finance_quote_deliveries')->value('recipient'));
        $this->assertInvalidDeliveryStateIsRejected();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('finance_quote_deliveries', 'uuid'));
        $uuid = DB::table('finance_quote_deliveries')->value('uuid');
        $this->assertIsString($uuid);
        $this->assertSame(substr($messageId, 1, 36), $uuid);
        $this->assertSame($storedBefore->recipient, DB::table('finance_quote_deliveries')->value('recipient'));
        $this->assertInvalidDeliveryStateIsRejected();
    }

    private function assertInvalidDeliveryStateIsRejected(): void
    {
        $existing = DB::table('finance_quote_deliveries')->first();
        $this->assertNotNull($existing);
        $uuid = 'ffffffff-ffff-4fff-afff-ffffffffffff';
        $row = [
            'user_id' => $existing->user_id,
            'document_series_id' => $existing->document_series_id,
            'document_revision_id' => $existing->document_revision_id,
            'recipient' => 'invalid-state@example.com',
            'recipient_domain' => 'example.com',
            'message_id' => '<'.$uuid.'@quotes.ledgerline>',
            'state' => 'delivered',
            'attempts' => 0,
            'last_error_code' => null,
            'queued_at' => now(),
            'sent_at' => null,
            'failed_at' => null,
        ];
        if (Schema::hasColumn('finance_quote_deliveries', 'uuid')) {
            $row['uuid'] = $uuid;
        }

        try {
            DB::table('finance_quote_deliveries')->insert($row);
            $this->fail('The delivery state constraint must survive UUID migration table rebuilds.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_retry_resumes_after_publication_completed_before_the_send_checkpoint(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $ownerId = (int) $owner->id;
        $this->actingAs($owner);
        $this->configureSmtp($ownerId);
        $quote = app(CreateQuote::class)->handle($ownerId, 'create-publication-gap', $this->draft());
        $sendKey = 'resume-publication-gap';
        $recipient = 'billing@example.com';
        $requestSha256 = hash('sha256', json_encode([
            'expected_version' => $quote->version,
            'quote_uuid' => $quote->id->uuid,
            'recipient' => $recipient,
        ], JSON_THROW_ON_ERROR));
        app(QuoteOperationRepository::class)->reserve(
            $ownerId,
            'send',
            $sendKey,
            $requestSha256,
            $quote->id,
        );
        $published = app(PublishQuote::class)->handle(new PublishQuoteData(
            $quote->id,
            $quote->version,
            'send-publish-'.hash('sha256', $sendKey),
        ));

        $resumed = app(SendQuote::class)->handle(new SendQuoteData(
            $quote->id,
            $quote->version,
            $sendKey,
        ));

        $this->assertSame($published->currentRevision?->id, $resumed->currentRevision?->id);
        $this->assertSame(1, DB::table('finance_document_revisions')->count());
        $this->assertSame(1, DB::table('finance_quote_deliveries')->count());
    }

    public function test_worker_sends_the_exact_immutable_pdf_with_stable_message_id_and_records_success(): void
    {
        Queue::fake();
        Mail::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-worker-quote', $this->draft());
        $sent = app(SendQuote::class)->handle(new SendQuoteData(
            $quote->id,
            $quote->version,
            'send-worker-quote',
        ));
        $delivery = $this->deliveryRow();
        $revision = $sent->currentRevision;
        $this->assertNotNull($revision);
        $this->assertIsString($revision->pdfPath);
        $pdfBytes = Storage::disk($this->documentDisk())->get($revision->pdfPath);
        $this->assertIsString($pdfBytes);

        (new DeliverQuoteRevision((int) $owner->id, $delivery->id))
            ->handle(app(CompanySmtpMailer::class));

        Mail::assertSent(QuoteRevisionMail::class, function (QuoteRevisionMail $mail) use (
            $delivery,
            $pdfBytes,
            $revision,
        ): bool {
            $this->assertTrue($mail->hasTo('billing@example.com'));
            $this->assertSame(trim($delivery->messageId, '<>'), $mail->headers()->messageId);
            $this->assertSame($revision->pdfSha256, hash('sha256', $mail->pdfBytes));
            $attachment = $mail->attachments()[0] ?? null;
            $this->assertNotNull($attachment);
            $attachment->attachWith(
                fn (): never => throw new RuntimeException('Expected an in-memory attachment.'),
                function (callable $data, Attachment $resolved) use ($pdfBytes): void {
                    $this->assertSame($pdfBytes, $data());
                    $this->assertSame('application/pdf', $resolved->mime);
                },
            );

            return true;
        });
        $this->assertDatabaseHas('finance_quote_deliveries', [
            'id' => $delivery->id,
            'state' => 'sent',
            'attempts' => 1,
            'last_error_code' => null,
        ]);
        $this->assertDatabaseHas('finance_document_activities', [
            'user_id' => $owner->id,
            'document_revision_id' => $revision->id,
            'type' => 'quote.mail.sent',
        ]);
        $activityPayloads = DB::table('finance_document_activities')
            ->whereIn('type', ['quote.mail.queued', 'quote.mail.sent'])
            ->pluck('payload')
            ->implode(' ');
        $this->assertStringNotContainsString('billing@example.com', $activityPayloads);
    }

    public function test_worker_uses_three_bounded_attempts_and_records_only_a_secret_free_final_failure(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-failing-worker', $this->draft());
        app(SendQuote::class)->handle(new SendQuoteData($quote->id, $quote->version, 'send-failing-worker'));
        $delivery = $this->deliveryRow();
        $transport = new FakeCompanyMailTransport(new SafePreAcceptMailFailure('secret billing@example.com smtp detail'));
        $smtp = new CompanySmtpMailer($transport);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $job = new DeliverQuoteRevision((int) $owner->id, $delivery->id);
            $fakeJob = new FakeJob;
            $fakeJob->attempts = $attempt;
            $job->setJob($fakeJob);

            try {
                $job->handle($smtp);
                if ($attempt < 3) {
                    $this->fail("Delivery attempt {$attempt} unexpectedly completed.");
                }
            } catch (RuntimeException $exception) {
                $this->assertLessThan(3, $attempt);
                $this->assertSame('quote_delivery_failed', $exception->getMessage());
                $this->assertStringNotContainsString('billing@example.com', $exception->getMessage());
            }

            $row = $this->deliveryRow();
            $this->assertSame($attempt, $row->attempts);
            $this->assertSame($attempt < 3 ? 'queued' : 'failed', $row->state);
        }

        $this->assertSame([60, 300, 900], (new DeliverQuoteRevision(1, 1))->backoff());
        $this->assertSame(3, $transport->calls);
        $this->assertDatabaseHas('finance_quote_deliveries', [
            'id' => $delivery->id,
            'state' => 'failed',
            'attempts' => 3,
            'last_error_code' => 'smtp_send_failed',
        ]);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('type', 'quote.mail.failed')
            ->count());
        $failurePayload = $this->failedActivityPayload();
        $this->assertStringNotContainsString('billing@example.com', $failurePayload);
        $this->assertStringNotContainsString('secret', $failurePayload);
        foreach ($transport->mailerNames as $mailerName) {
            $this->assertNull(config("mail.mailers.{$mailerName}"));
            $this->assertNull(config("mail.from.{$mailerName}"));
        }
    }

    public function test_ambiguous_failure_after_possible_acceptance_is_uncertain_and_never_retried(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-ambiguous-worker', $this->draft());
        app(SendQuote::class)->handle(new SendQuoteData($quote->id, $quote->version, 'send-ambiguous-worker'));
        $delivery = $this->deliveryRow();
        $acceptedThenFailed = new FakeCompanyMailTransport(
            new UncertainMailTransportFailure('transport accepted bytes before the connection was lost'),
            acceptedBeforeFailure: true,
        );
        $smtp = new CompanySmtpMailer($acceptedThenFailed);

        (new DeliverQuoteRevision((int) $owner->id, $delivery->id))->handle($smtp);
        (new DeliverQuoteRevision((int) $owner->id, $delivery->id))->handle($smtp);

        $this->assertSame(1, $acceptedThenFailed->calls);
        $this->assertSame(1, $acceptedThenFailed->acceptedCalls);
        $this->assertDatabaseHas('finance_quote_deliveries', [
            'id' => $delivery->id,
            'state' => 'failed',
            'attempts' => 1,
            'last_error_code' => 'delivery_outcome_uncertain',
        ]);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('type', 'quote.mail.uncertain')
            ->count());
    }

    public function test_user_retry_after_final_failure_keeps_the_same_revision_and_pdf(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-retry-delivery', $this->draft());
        $sent = app(SendQuote::class)->handle(new SendQuoteData(
            $quote->id,
            $quote->version,
            'send-first-delivery',
        ));
        $firstDelivery = $this->deliveryRow();
        $failing = new FakeCompanyMailTransport(new SafePreAcceptMailFailure('SMTP unavailable'));
        $failedJob = new DeliverQuoteRevision((int) $owner->id, $firstDelivery->id);
        $queueJob = new FakeJob;
        $queueJob->attempts = 3;
        $failedJob->setJob($queueJob);
        $failedJob->handle(new CompanySmtpMailer($failing));
        $revisionCount = DB::table('finance_document_revisions')->count();
        $pdfPath = $sent->currentRevision?->pdfPath;
        $pdfSha256 = $sent->currentRevision?->pdfSha256;

        $retried = app(SendQuote::class)->handle(new SendQuoteData(
            $sent->id,
            $sent->version,
            'send-retry-delivery',
        ));

        $this->assertSame($revisionCount, DB::table('finance_document_revisions')->count());
        $this->assertSame($sent->currentRevision?->id, $retried->currentRevision?->id);
        $this->assertSame($pdfPath, $retried->currentRevision?->pdfPath);
        $this->assertSame($pdfSha256, $retried->currentRevision?->pdfSha256);
        $this->assertSame(2, DB::table('finance_quote_deliveries')->count());
        $this->assertSame(1, DB::table('finance_quote_deliveries')
            ->where('document_revision_id', $sent->currentRevision?->id)
            ->where('state', 'queued')
            ->count());
    }

    public function test_crash_after_transport_acceptance_becomes_uncertain_and_is_never_automatically_resent(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-uncertain-worker', $this->draft());
        app(SendQuote::class)->handle(new SendQuoteData($quote->id, $quote->version, 'send-uncertain-worker'));
        $delivery = $this->deliveryRow();
        $accepted = new FakeCompanyMailTransport;
        $smtp = new CompanySmtpMailer($accepted);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER quote_delivery_sent_crash
            BEFORE UPDATE OF state ON finance_quote_deliveries
            WHEN NEW.state = 'sent'
            BEGIN
                SELECT RAISE(ABORT, 'injected post-send crash');
            END
            SQL);

        try {
            (new DeliverQuoteRevision((int) $owner->id, $delivery->id))->handle($smtp);
            $this->fail('The injected post-send persistence crash did not occur.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('injected post-send crash', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER quote_delivery_sent_crash');
        }
        $this->assertSame(1, $accepted->calls);
        $this->assertDatabaseHas('finance_quote_deliveries', [
            'id' => $delivery->id,
            'state' => 'sending',
            'attempts' => 1,
        ]);

        (new DeliverQuoteRevision((int) $owner->id, $delivery->id))->handle($smtp);

        $this->assertSame(1, $accepted->calls);
        $this->assertDatabaseHas('finance_quote_deliveries', [
            'id' => $delivery->id,
            'state' => 'failed',
            'attempts' => 1,
            'last_error_code' => 'delivery_outcome_uncertain',
        ]);
        $this->assertDatabaseHas('finance_document_activities', [
            'user_id' => $owner->id,
            'type' => 'quote.mail.uncertain',
        ]);
    }

    public function test_worker_refuses_a_delivery_after_its_revision_is_no_longer_current(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-stale-delivery', $this->draft());
        $first = app(SendQuote::class)->handle(new SendQuoteData(
            $quote->id,
            $quote->version,
            'send-stale-delivery',
        ));
        $delivery = $this->deliveryRow();
        $version = app(StartQuoteVersion::class)->handle($first->id, $first->version);
        app(PublishQuote::class)->handle(new PublishQuoteData(
            $version->id,
            $version->version,
            'publish-replacement-before-delivery',
        ));
        $transport = new FakeCompanyMailTransport;

        (new DeliverQuoteRevision((int) $owner->id, $delivery->id))
            ->handle(new CompanySmtpMailer($transport));

        $this->assertSame(0, $transport->calls);
        $this->assertDatabaseHas('finance_quote_deliveries', [
            'id' => $delivery->id,
            'state' => 'failed',
            'attempts' => 0,
            'last_error_code' => 'quote_revision_stale',
        ]);
        $this->assertDatabaseHas('finance_document_activities', [
            'document_revision_id' => $delivery->documentRevisionId,
            'type' => 'quote.mail.failed',
        ]);
    }

    public function test_company_smtp_runtime_configuration_is_owner_isolated_and_always_torn_down(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->configureSmtp((int) $first->id, 'smtp.first.example', 'first-secret');
        $this->configureSmtp((int) $second->id, 'smtp.second.example', 'second-secret');
        $transport = new FakeCompanyMailTransport;
        $smtp = new CompanySmtpMailer($transport);
        $mail = fn (string $id): QuoteRevisionMail => new QuoteRevisionMail(
            $id,
            'AN-2026-0001',
            'AN-2026-0001',
            '2026-09-27',
            '%PDF-owner-isolation',
            'AN-2026-0001.pdf',
            ['name' => 'Test', 'address' => 'quotes@example.com'],
        );

        $smtp->send((int) $first->id, 'one@example.net', $mail('<one@quotes.ledgerline>'));
        $smtp->send((int) $second->id, 'two@example.net', $mail('<two@quotes.ledgerline>'));

        $this->assertSame(['smtp.first.example', 'smtp.second.example'], $transport->hosts);
        $this->assertSame(['first-secret', 'second-secret'], $transport->passwords);
        $this->assertCount(2, array_unique($transport->mailerNames));
        foreach ($transport->mailerNames as $mailerName) {
            $this->assertNull(config("mail.mailers.{$mailerName}"));
            $this->assertNull(config("mail.from.{$mailerName}"));
        }
        $serializedConfig = json_encode(config('mail'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('first-secret', $serializedConfig);
        $this->assertStringNotContainsString('second-secret', $serializedConfig);
    }

    public function test_company_smtp_secrets_are_removed_even_when_mailer_purge_throws(): void
    {
        $owner = User::factory()->create();
        $this->configureSmtp((int) $owner->id, 'smtp.cleanup.example', 'cleanup-secret');
        $transport = new FakeCompanyMailTransport;
        $smtp = new CompanySmtpMailer($transport);
        $mail = new QuoteRevisionMail(
            '<cleanup@quotes.ledgerline>',
            'AN-2026-0001',
            'AN-2026-0001',
            '2026-09-27',
            '%PDF-cleanup',
            'AN-2026-0001.pdf',
            ['name' => 'Test', 'address' => 'quotes@example.com'],
        );
        $originalManager = app('mail.manager');
        $purgingManager = new class($this->app) extends MailManager
        {
            public function purge(mixed $name = null): void
            {
                throw new RuntimeException('injected purge failure');
            }
        };
        app()->instance('mail.manager', $purgingManager);

        try {
            $smtp->send((int) $owner->id, 'cleanup@example.net', $mail);
            $this->fail('The injected mail manager purge failure did not occur.');
        } catch (UncertainMailTransportFailure $exception) {
            $this->assertSame('injected purge failure', $exception->getPrevious()?->getMessage());
        } finally {
            app()->instance('mail.manager', $originalManager);
        }

        $this->assertSame(1, $transport->calls);
        $mailerName = $transport->mailerNames[0];
        $this->assertNull(config("mail.mailers.{$mailerName}"));
        $this->assertNull(config("mail.from.{$mailerName}"));
        $this->assertStringNotContainsString('cleanup-secret', json_encode(config('mail'), JSON_THROW_ON_ERROR));
    }

    public function test_existing_published_revision_is_queued_without_another_revision_or_pdf(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $draft = app(CreateQuote::class)->handle((int) $owner->id, 'create-existing-revision', $this->draft());
        $published = app(PublishQuote::class)->handle(new PublishQuoteData(
            $draft->id,
            $draft->version,
            'publish-existing-revision',
        ));
        $revisionCount = DB::table('finance_document_revisions')->count();
        $pdfPath = $published->currentRevision?->pdfPath;

        $sent = app(SendQuote::class)->handle(new SendQuoteData(
            $published->id,
            $published->version,
            'send-existing-revision',
        ));

        $this->assertSame($published->currentRevision?->id, $sent->currentRevision?->id);
        $this->assertSame($revisionCount, DB::table('finance_document_revisions')->count());
        $this->assertIsString($pdfPath);
        $this->assertSame($pdfPath, $sent->currentRevision?->pdfPath);
        $this->assertSame(1, DB::table('finance_quote_deliveries')->count());
    }

    public function test_same_key_replays_one_delivery_and_changed_recipient_is_rejected(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-replayed-send', $this->draft());
        $command = app(SendQuote::class);
        $data = new SendQuoteData($quote->id, $quote->version, 'replayed-send');

        $first = $command->handle($data);
        $replay = $command->handle($data);

        $this->assertSame($first->currentRevision?->id, $replay->currentRevision?->id);
        $this->assertSame(1, DB::table('finance_quote_deliveries')->count());
        Queue::assertPushed(DeliverQuoteRevision::class, 1);

        try {
            $command->handle(new SendQuoteData(
                $quote->id,
                $quote->version,
                'replayed-send',
                'other@example.net',
            ));
            $this->fail('A reused send key accepted a different recipient.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }
    }

    public function test_missing_recipient_and_smtp_fail_before_publication_or_dispatch(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $withoutRecipient = app(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-no-recipient',
            $this->draft(null),
        );

        $this->assertActionError(
            'no_recipient',
            fn () => app(SendQuote::class)->handle(new SendQuoteData(
                $withoutRecipient->id,
                $withoutRecipient->version,
                'send-no-recipient',
            )),
        );

        $withoutSmtp = app(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-no-smtp',
            $this->draft(),
        );
        $this->assertActionError(
            'no_smtp',
            fn () => app(SendQuote::class)->handle(new SendQuoteData(
                $withoutSmtp->id,
                $withoutSmtp->version,
                'send-no-smtp',
            )),
        );

        $this->assertSame(0, DB::table('finance_document_revisions')->count());
        $this->assertSame(0, DB::table('finance_quote_deliveries')->count());
        Queue::assertNothingPushed();
    }

    public function test_missing_immutable_pdf_and_render_failure_never_queue_mail(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->configureSmtp((int) $owner->id);
        $draft = app(CreateQuote::class)->handle((int) $owner->id, 'create-missing-pdf', $this->draft());
        $published = app(PublishQuote::class)->handle(new PublishQuoteData(
            $draft->id,
            $draft->version,
            'publish-missing-pdf',
        ));
        $path = $published->currentRevision?->pdfPath;
        $this->assertIsString($path);
        Storage::disk($this->documentDisk())->delete($path);

        $this->assertActionError(
            'no_pdf',
            fn () => app(SendQuote::class)->handle(new SendQuoteData(
                $published->id,
                $published->version,
                'send-missing-pdf',
            )),
        );

        $renderDraft = app(CreateQuote::class)->handle((int) $owner->id, 'create-render-failure', $this->draft());
        app()->instance(DocumentRenderer::class, new class implements DocumentRenderer
        {
            public function render(array $snapshot): string
            {
                throw new RuntimeException('Injected renderer failure.');
            }
        });

        try {
            app(SendQuote::class)->handle(new SendQuoteData(
                $renderDraft->id,
                $renderDraft->version,
                'send-render-failure',
            ));
            $this->fail('A failed render unexpectedly queued mail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected renderer failure.', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('finance_quote_deliveries')->count());
        Queue::assertNothingPushed();
    }

    public function test_foreign_owner_quote_id_is_hidden_before_delivery_validation(): void
    {
        Queue::fake();
        $this->configureLocalDocumentDisk();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = app(CreateQuote::class)->handle((int) $owner->id, 'create-owner-quote', $this->draft());
        $other = User::factory()->create();
        $foreign = new QuoteId(
            (int) $other->id,
            $quote->id->uuid,
        );

        $this->expectException(ModelNotFoundException::class);
        app(SendQuote::class)->handle(new SendQuoteData($foreign, 0, 'foreign-send'));
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresDeliverySchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run Quote delivery concurrency tests.',
            );
        }
        $postgresConfig = config('database.connections.pgsql');
        if (! is_array($postgresConfig)) {
            throw new RuntimeException('PostgreSQL connection configuration is unavailable.');
        }

        $defaultConnection = DB::getDefaultConnection();
        $connectionName = 'pgsql_quote_delivery_concurrency';
        $schema = 'finance_quote_task8_'.bin2hex(random_bytes(8));
        $diskName = 'quote-delivery-pg';
        $diskRoot = storage_path('framework/testing/'.$diskName.'-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($diskRoot);
        config([
            "database.connections.{$connectionName}" => array_merge(
                $postgresConfig,
                ['url' => $postgresUrl, 'search_path' => 'public'],
            ),
            'files.disk' => $diskName,
            "filesystems.disks.{$diskName}" => [
                'driver' => 'local',
                'root' => $diskRoot,
                'throw' => true,
            ],
        ]);
        Storage::forgetDisk($diskName);
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
            Schema::create('finance_partners', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            });
            Schema::create('invoices', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            });
            Schema::create('user_settings', static function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
                $table->boolean('company_smtp_enabled')->default(false);
                $table->string('company_smtp_host')->nullable();
                $table->unsignedSmallInteger('company_smtp_port')->nullable();
                $table->string('company_smtp_encryption')->nullable();
                $table->string('company_smtp_username')->nullable();
                $table->text('company_smtp_password')->nullable();
                $table->string('company_smtp_from_address')->nullable();
                $table->string('company_smtp_from_name')->nullable();
                $table->string('company_name')->nullable();
            });
            Schema::create('jobs', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
            Schema::create('cache', static function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
            Schema::create('cache_locks', static function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
            foreach ([
                '2026_08_28_100000_create_finance_document_core.php',
                '2027_03_03_100000_create_finance_quote_workflow.php',
                '2027_03_04_120000_add_uuid_to_finance_quote_deliveries.php',
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
                'company_smtp_enabled' => true,
                'company_smtp_host' => 'smtp.example.com',
                'company_smtp_port' => 587,
                'company_smtp_encryption' => 'tls',
                'company_smtp_username' => null,
                'company_smtp_password' => null,
                'company_smtp_from_address' => 'quotes@example.com',
                'company_smtp_from_name' => 'Ada Networks',
                'company_name' => 'Ada Networks',
            ]);

            $test($postgresUrl, $schema);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');
            Storage::forgetDisk($diskName);
            File::deleteDirectory($diskRoot);

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

    /** @return array{quote_uuid: string, disk_root: string} */
    private function storedPostgresPublishedQuote(): array
    {
        $quoteUuid = '018f4ca3-224d-7d8d-9f08-000000000001';
        $bytes = '%PDF-concurrent-quote-delivery';
        $digest = hash('sha256', $bytes);
        $path = 'finance/revisions/'.substr($digest, 0, 2).'/'.$digest.'.pdf';
        Storage::disk($this->documentDisk())->put($path, $bytes);
        $now = '2026-08-28 10:00:00';
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => 1,
            'uuid' => $quoteUuid,
            'document_type' => 'quote',
            'status' => 'sent',
            'source_type' => null,
            'source_id' => null,
            'created_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => 1,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode([
                'document_number' => 'AN-2026-0001',
                'revision_label' => 'AN-2026-0001',
                'valid_until' => '2026-09-27',
                'customer' => ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            ], JSON_THROW_ON_ERROR),
            'net_minor' => 10_000,
            'vat_minor' => 1_900,
            'gross_minor' => 11_900,
            'currency' => 'EUR',
            'change_reason' => null,
            'pdf_path' => $path,
            'pdf_sha256' => $digest,
            'published_at' => $now,
            'created_by' => 1,
            'created_at' => $now,
        ]);
        DB::table('finance_quote_series')->insert([
            'document_series_id' => $seriesId,
            'user_id' => 1,
            'document_type' => 'quote',
            'partner_id' => null,
            'current_revision_id' => $revisionId,
            'number' => 'AN-2026-0001',
            'sequence_year' => 2026,
            'sequence_number' => 1,
            'version' => 1,
            'published_at' => $now,
            'accepted_at' => null,
            'declined_at' => null,
            'converted_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $diskRoot = config('filesystems.disks.'.$this->documentDisk().'.root');
        if (! is_string($diskRoot)) {
            throw new RuntimeException('PostgreSQL delivery disk root is unavailable.');
        }

        return ['quote_uuid' => $quoteUuid, 'disk_root' => $diskRoot];
    }

    private function startPostgresDeliveryWorker(
        string $postgresUrl,
        string $schema,
        string $diskRoot,
        string $worker,
        string $quoteUuid,
    ): Process {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $diskRoot = getenv('FINANCE_TEST_QUOTE_DISK_ROOT');
            $worker = getenv('FINANCE_TEST_QUOTE_WORKER');
            $quoteUuid = getenv('FINANCE_TEST_QUOTE_UUID');
            if (! is_string($url) || ! is_string($schema) || ! is_string($diskRoot)
                || ! is_string($worker) || ! is_string($quoteUuid)
                || preg_match('/\Afinance_quote_task8_[0-9a-f]{16}\z/D', $schema) !== 1) {
                fwrite(STDERR, 'invalid-postgres-delivery-worker-configuration');
                exit(90);
            }

            $base = config('database.connections.pgsql');
            $base = is_array($base) ? $base : [];
            foreach (['pgsql_quote_worker', 'pgsql_quote_barrier'] as $connectionName) {
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
            \Illuminate\Support\Facades\DB::setDefaultConnection('pgsql_quote_worker');
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
            config([
                'queue.default' => 'database',
                'queue.connections.database.connection' => 'pgsql_quote_worker',
                'queue.connections.database.table' => 'jobs',
                'cache.default' => 'database',
                'cache.stores.database.connection' => 'pgsql_quote_worker',
                'cache.stores.database.table' => 'cache',
                'cache.stores.database.lock_table' => 'cache_locks',
                'files.disk' => 'quote-delivery-pg-worker',
                'filesystems.disks.quote-delivery-pg-worker' => [
                    'driver' => 'local',
                    'root' => $diskRoot,
                    'throw' => true,
                ],
            ]);
            \Illuminate\Support\Facades\Cache::forgetDriver('database');
            $barrier = \Illuminate\Support\Facades\DB::connection('pgsql_quote_barrier');
            $barrier->table('finance_task8_delivery_barrier')->insert(['worker' => $worker]);
            $deadline = microtime(true) + 10.0;
            while ((int) $barrier->table('finance_task8_delivery_barrier')->count() < 2) {
                if (microtime(true) >= $deadline) {
                    fwrite(STDERR, 'postgres-delivery-barrier-timeout');
                    exit(91);
                }
                usleep(20_000);
            }

            $owner = new \App\Models\User;
            $owner->forceFill(['id' => 1]);
            \Illuminate\Support\Facades\Auth::setUser($owner);
            $mailer = new class implements \App\Modules\Finance\Application\Ports\Quotes\QuoteMailer {
                public function assertConfigured(int $ownerId): void {}
                public function assertRevisionReady(\App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef $revision): void {}
                public function dispatch(int $ownerId, int $deliveryId): void
                {
                    \App\Modules\Finance\Infrastructure\Mail\Jobs\DeliverQuoteRevision::dispatch(
                        $ownerId,
                        $deliveryId,
                    )->afterCommit();
                }
            };
            $command = new \App\Modules\Finance\Application\Commands\Quotes\SendQuote(
                $app->make(\App\Modules\Finance\Application\Ports\Quotes\QuoteRepository::class),
                $app->make(\App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository::class),
                $mailer,
                $app->make(\App\Modules\Finance\Application\Commands\Quotes\PublishQuote::class),
            );

            try {
                $result = $command->handle(new \App\Modules\Finance\Application\DTOs\Quotes\SendQuoteData(
                    new \App\Modules\Finance\Application\DTOs\Quotes\QuoteId(1, $quoteUuid),
                    1,
                    'postgres-concurrent-delivery-key',
                ));
                echo json_encode([
                    'revision_id' => $result->currentRevision?->id,
                ], JSON_THROW_ON_ERROR);
                exit(0);
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
                'FINANCE_TEST_QUOTE_DISK_ROOT' => $diskRoot,
                'FINANCE_TEST_QUOTE_WORKER' => $worker,
                'FINANCE_TEST_QUOTE_UUID' => $quoteUuid,
            ],
            null,
            25,
        );
        $process->start();

        return $process;
    }

    private function draft(?string $email = 'billing@example.com'): QuoteDraftData
    {
        return new QuoteDraftData(
            title: 'Network refresh',
            partnerId: null,
            customer: array_filter(
                ['name' => 'Ada GmbH', 'email' => $email],
                static fn (mixed $value): bool => $value !== null,
            ),
            issueDate: '2026-08-28',
            validUntil: '2026-09-27',
            currency: 'EUR',
            lines: [new QuoteLineData('Consulting', '2.5000', 'hour', '100.00', '19.00', 'service', null)],
            discountType: 'none',
            discountValue: null,
            introText: null,
            outroText: null,
            internalNote: null,
        );
    }

    public function test_postgresql_concurrent_same_key_creates_one_delivery_and_one_transport_job(): void
    {
        $this->withIsolatedPostgresDeliverySchema(function (string $postgresUrl, string $schema): void {
            $aggregate = $this->storedPostgresPublishedQuote();
            DB::statement('CREATE TABLE finance_task8_delivery_barrier (worker varchar(32) PRIMARY KEY)');
            $processes = [
                $this->startPostgresDeliveryWorker($postgresUrl, $schema, $aggregate['disk_root'], 'first', $aggregate['quote_uuid']),
                $this->startPostgresDeliveryWorker($postgresUrl, $schema, $aggregate['disk_root'], 'second', $aggregate['quote_uuid']),
            ];
            $results = [];

            foreach ($processes as $process) {
                $exitCode = $process->wait();
                $this->assertSame(0, $exitCode, $process->getErrorOutput().$process->getOutput());
                $result = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                $results[] = $result;
            }

            $this->assertSame($results[0]['revision_id'], $results[1]['revision_id']);
            $this->assertSame(1, DB::table('finance_quote_deliveries')->count());
            $this->assertSame(1, DB::table('jobs')->count());
            $delivery = DB::table('finance_quote_deliveries')->sole();
            $this->assertIsObject($delivery);
            $uuid = data_get($delivery, 'uuid');
            $messageId = data_get($delivery, 'message_id');
            $deliveryId = data_get($delivery, 'id');
            $this->assertIsString($uuid);
            $this->assertSame('<'.$uuid.'@quotes.ledgerline>', $messageId);
            $this->assertIsInt($deliveryId);

            $transport = new FakeCompanyMailTransport;
            (new DeliverQuoteRevision(1, $deliveryId))->handle(new CompanySmtpMailer($transport));

            $this->assertSame(1, $transport->calls);
            $this->assertDatabaseHas('finance_quote_deliveries', [
                'id' => $deliveryId,
                'state' => 'sent',
                'message_id' => '<'.$uuid.'@quotes.ledgerline>',
            ]);
        });
    }

    /** @param callable(): mixed $operation */
    private function assertActionError(string $expected, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected quote action error {$expected}.");
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame($expected, $exception->errorCode);
        }
    }

    private function configureSmtp(
        int $ownerId,
        string $host = 'smtp.example.com',
        ?string $password = null,
    ): void {
        UserSetting::query()->create([
            'user_id' => $ownerId,
            'company_name' => 'Ada Networks',
            'company_smtp_enabled' => true,
            'company_smtp_host' => $host,
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
            'company_smtp_from_address' => 'quotes@example.com',
            'company_smtp_from_name' => 'Ada Networks',
            'company_smtp_password' => $password,
        ]);
    }

    private function deliveryRow(): QuoteDeliveryTestRow
    {
        $row = DB::table('finance_quote_deliveries')->sole();
        if (! is_object($row)) {
            throw new RuntimeException('Expected one quote delivery row.');
        }
        $values = get_object_vars($row);
        $id = $values['id'] ?? null;
        $userId = $values['user_id'] ?? null;
        $documentRevisionId = $values['document_revision_id'] ?? null;
        $recipient = $values['recipient'] ?? null;
        $recipientDomain = $values['recipient_domain'] ?? null;
        $state = $values['state'] ?? null;
        $messageId = $values['message_id'] ?? null;
        $attempts = $values['attempts'] ?? null;
        if (! is_int($id)
            || ! is_int($userId)
            || ! is_int($documentRevisionId)
            || ! is_string($recipient)
            || ! is_string($recipientDomain)
            || ! is_string($state)
            || ! is_string($messageId)
            || ! is_int($attempts)) {
            throw new RuntimeException('Quote delivery row contains unexpected values.');
        }

        return new QuoteDeliveryTestRow(
            $id,
            $userId,
            $documentRevisionId,
            $recipient,
            $recipientDomain,
            $state,
            $messageId,
            $attempts,
        );
    }

    private function revisionRow(): QuoteRevisionTestRow
    {
        $row = DB::table('finance_document_revisions')->sole();
        if (! is_object($row)) {
            throw new RuntimeException('Expected one quote revision row.');
        }
        $values = get_object_vars($row);
        $id = $values['id'] ?? null;
        $pdfPath = $values['pdf_path'] ?? null;
        if (! is_int($id) || ! is_string($pdfPath)) {
            throw new RuntimeException('Quote revision row contains unexpected values.');
        }

        return new QuoteRevisionTestRow($id, $pdfPath);
    }

    private function failedActivityPayload(): string
    {
        $payload = DB::table('finance_document_activities')
            ->where('type', 'quote.mail.failed')
            ->value('payload');
        if (! is_string($payload)) {
            throw new RuntimeException('Expected a serialized failure activity payload.');
        }

        return $payload;
    }

    private function documentDisk(): string
    {
        $disk = config('files.disk');
        if (! is_string($disk)) {
            throw new RuntimeException('Expected a configured document disk.');
        }

        return $disk;
    }

    private function configureLocalDocumentDisk(): void
    {
        $diskName = 'quote-delivery-pdfs';
        $root = storage_path('framework/testing/'.$diskName.'-'.bin2hex(random_bytes(8)));
        $lockRoot = storage_path('framework/finance-document-locks/'.hash('sha256', $root));
        File::ensureDirectoryExists($root);
        config()->set('files.disk', $diskName);
        config()->set('filesystems.disks.'.$diskName, [
            'driver' => 'local',
            'root' => $root,
            'throw' => true,
        ]);
        Storage::forgetDisk($diskName);
        $this->beforeApplicationDestroyed(static function () use ($root, $lockRoot, $diskName): void {
            Storage::forgetDisk($diskName);
            File::deleteDirectory($root);
            File::deleteDirectory($lockRoot);
        });
    }
}

final readonly class QuoteDeliveryTestRow
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $documentRevisionId,
        public string $recipient,
        public string $recipientDomain,
        public string $state,
        public string $messageId,
        public int $attempts,
    ) {}
}

final readonly class QuoteRevisionTestRow
{
    public function __construct(
        public int $id,
        public string $pdfPath,
    ) {}
}

final class FakeCompanyMailTransport implements CompanyMailTransport
{
    public int $calls = 0;

    public int $acceptedCalls = 0;

    /** @var list<string> */
    public array $mailerNames = [];

    /** @var list<string> */
    public array $hosts = [];

    /** @var list<string|null> */
    public array $passwords = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
        private readonly bool $acceptedBeforeFailure = false,
    ) {}

    public function send(string $mailerName, string $recipient, Mailable $mail): CompanyMailTransportResult
    {
        $this->calls++;
        $this->mailerNames[] = $mailerName;
        $host = config("mail.mailers.{$mailerName}.host");
        if (! is_string($host)) {
            throw new RuntimeException('Expected a configured SMTP host.');
        }
        $this->hosts[] = $host;
        $password = config("mail.mailers.{$mailerName}.password");
        $this->passwords[] = is_string($password) ? $password : null;

        if ($this->acceptedBeforeFailure) {
            $this->acceptedCalls++;
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->acceptedCalls++;

        return CompanyMailTransportResult::accepted();
    }
}

final class ResumableQuoteMailer implements QuoteMailer
{
    public int $dispatches = 0;

    public function assertConfigured(int $ownerId): void {}

    public function assertRevisionReady(QuoteRevisionRef $revision): void {}

    public function dispatch(int $ownerId, int $deliveryId): void
    {
        $this->dispatches++;
        if ($this->dispatches === 1) {
            throw new RuntimeException('Injected dispatch failure.');
        }
    }
}
