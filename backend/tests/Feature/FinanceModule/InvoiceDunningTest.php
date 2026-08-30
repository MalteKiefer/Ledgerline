<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Invoices\QueueInvoiceReminder;
use App\Modules\Finance\Application\Commands\Invoices\RetryInvoiceDelivery;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Queries\InvoiceAgingQuery;
use App\Modules\Finance\Infrastructure\Mail\CompanyInvoiceMailer;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransport;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransportResult;
use App\Modules\Finance\Infrastructure\Mail\CompanySmtpMailer;
use App\Modules\Finance\Infrastructure\Mail\InvoiceRevisionMail;
use App\Modules\Finance\Infrastructure\Scheduling\SendInvoiceDeliveryJob;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InvoiceDunningTest extends TestCase
{
    use RefreshDatabase;

    public function test_aging_uses_owner_timezone_and_only_buckets_positive_open_sent_invoices(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update(['timezone' => 'Europe/Berlin']);
        $oneDay = $this->invoice($owner, 'sent', '2026-08-29', 11_900, 0, '000000000001');
        $partial = $this->invoice($owner, 'sent', '2026-07-15', 25_000, 16_900, '000000000002');
        $this->invoice($owner, 'finalized', '2026-07-01', 10_000, 0, '000000000003');
        $this->invoice($owner, 'sent', '2026-07-01', 10_000, 10_000, '000000000004');
        $this->invoice($owner, 'sent', '2026-08-31', 10_000, 0, '000000000005');

        $aging = app(InvoiceAgingQuery::class)->handle(
            new DateTimeImmutable('2026-08-29T22:30:00+00:00'),
        );

        $this->assertSame([$oneDay->value], array_column($aging['days_1_30'], 'invoice_id'));
        $this->assertSame([$partial->value], array_column($aging['days_31_60'], 'invoice_id'));
        $this->assertSame(11_900, $aging['totals']['days_1_30_minor']);
        $this->assertSame(8_100, $aging['totals']['days_31_60_minor']);
        $this->assertSame(20_000, $aging['totals']['open_minor']);
    }

    public function test_aging_bucket_boundaries_are_exact_at_30_31_60_and_61_days(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update(['timezone' => 'UTC']);
        $day30 = $this->invoice($owner, 'sent', '2026-07-30', 1_000, 0, '000000000006');
        $day31 = $this->invoice($owner, 'sent', '2026-07-29', 2_000, 0, '000000000007');
        $day60 = $this->invoice($owner, 'sent', '2026-06-30', 3_000, 0, '000000000008');
        $day61 = $this->invoice($owner, 'sent', '2026-06-29', 4_000, 0, '000000000009');

        $aging = app(InvoiceAgingQuery::class)->handle(
            new DateTimeImmutable('2026-08-29T12:00:00+00:00'),
        );

        $this->assertSame([$day30->value], array_column($aging['days_1_30'], 'invoice_id'));
        $this->assertSame(
            [$day60->value, $day31->value],
            array_column($aging['days_31_60'], 'invoice_id'),
        );
        $this->assertSame([$day61->value], array_column($aging['days_61_plus'], 'invoice_id'));
        $this->assertSame(1_000, $aging['totals']['days_1_30_minor']);
        $this->assertSame(5_000, $aging['totals']['days_31_60_minor']);
        $this->assertSame(4_000, $aging['totals']['days_61_plus_minor']);
        $this->assertSame(10_000, $aging['totals']['open_minor']);
    }

    public function test_overdue_reminder_is_idempotent_per_level_and_records_one_successful_history_entry(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update([
            'timezone' => 'UTC',
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.com',
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
            'company_smtp_from_address' => 'billing@example.com',
            'company_smtp_from_name' => 'Ledgerline GmbH',
        ]);
        $invoice = $this->invoice($owner, 'sent', '2026-08-01', 11_900, 0, '000000000010');
        $mailer = new DunningRecordingMailer;
        app()->instance(InvoiceMailer::class, $mailer);
        $command = app(QueueInvoiceReminder::class);
        $at = new DateTimeImmutable('2026-08-29T12:00:00+00:00');

        $first = $command->handle($invoice, 1, null, $at);
        $replay = $command->handle($invoice, 1, null, $at);

        $this->assertSame($first->value, $replay->value);
        $this->assertDatabaseCount('finance_invoice_deliveries', 1);
        $transport = new DunningTransport;
        (new SendInvoiceDeliveryJob((int) $owner->id, $first->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));
        (new SendInvoiceDeliveryJob((int) $owner->id, $first->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));

        $this->assertSame(1, $transport->calls);
        $this->assertTrue($transport->mail?->reminder ?? false);
        $this->assertSame('Customer', $transport->mail?->customer);
        // SendInvoiceDeliveryJob::daysOverdue() deliberately measures from the
        // real send-time clock (a reminder mailed days after being queued must
        // report how overdue the invoice is *now*, not when it was queued) —
        // it does not read $at. A hardcoded day count here would drift by one
        // every day this test runs on a date other than the day it was written.
        $expectedDaysOverdue = (int) (new DateTimeImmutable('2026-08-01'))
            ->diff(new DateTimeImmutable('today', new DateTimeZone('UTC')))
            ->format('%a');
        $this->assertSame($expectedDaysOverdue, $transport->mail?->daysOverdue);
        $this->assertSame('119.00 EUR', $transport->mail?->openAmount);
        $activities = DB::table('finance_document_activities')
            ->where('type', 'invoice.reminder.sent')
            ->get();
        $this->assertCount(1, $activities);
        $this->assertStringContainsString('"level":1', (string) $activities->first()?->payload);
    }

    public function test_exact_reminder_replay_precedes_paid_pdf_smtp_and_aging_preflight_but_mismatch_conflicts(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update(['timezone' => 'UTC']);
        $invoice = $this->invoice($owner, 'sent', '2026-08-01', 11_900, 0, '000000000011');
        $mailer = new DunningRecordingMailer;
        app()->instance(InvoiceMailer::class, $mailer);
        $command = app(QueueInvoiceReminder::class);
        $at = new DateTimeImmutable('2026-08-29T12:00:00+00:00');
        $first = $command->handle($invoice, 1, 'customer@example.test', $at);
        DB::table('finance_invoice_deliveries')->where('id', $first->value)->update([
            'status' => 'sent', 'sent_at' => now(),
        ]);
        DB::table('finance_invoices')->where('id', $invoice->value)->update([
            'allocated_minor' => 11_900, 'open_minor' => 0,
        ]);
        $mailer->configured = false;

        $replay = $command->handle($invoice, 1, 'customer@example.test', $at);

        $this->assertSame($first->value, $replay->value);
        try {
            $command->handle($invoice, 1, 'changed@example.test', $at);
            $this->fail('A changed reminder occurrence payload was replayed.');
        } catch (\DomainException $exception) {
            $this->assertSame('delivery_idempotency_conflict', $exception->getMessage());
        }
    }

    public function test_reminder_rejects_future_due_finalized_and_paid_invoices_without_history(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        app()->instance(InvoiceMailer::class, new DunningRecordingMailer);
        $future = $this->invoice($owner, 'sent', '2026-09-01', 11_900, 0, '000000000020');
        $finalized = $this->invoice($owner, 'finalized', '2026-08-01', 11_900, 0, '000000000021');
        $paid = $this->invoice($owner, 'sent', '2026-08-01', 11_900, 11_900, '000000000022');
        $command = app(QueueInvoiceReminder::class);
        $at = new DateTimeImmutable('2026-08-29T12:00:00+00:00');

        foreach ([$future, $finalized, $paid] as $index => $invoice) {
            try {
                $command->handle($invoice, 1, null, $at);
                $this->fail('An ineligible reminder was queued.');
            } catch (\DomainException $exception) {
                $this->assertSame('invoice_not_overdue', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('finance_invoice_deliveries', 0);
        $this->assertDatabaseMissing('finance_document_activities', ['type' => 'invoice.reminder.queued']);
    }

    public function test_queued_reminder_rechecks_balance_under_lock_before_transport(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update([
            'timezone' => 'UTC',
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.com',
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
            'company_smtp_from_address' => 'billing@example.com',
        ]);
        $invoice = $this->invoice($owner, 'sent', '2026-08-01', 11_900, 0, '000000000030');
        app()->instance(InvoiceMailer::class, new DunningRecordingMailer);
        $delivery = app(QueueInvoiceReminder::class)->handle(
            $invoice,
            1,
            null,
            new DateTimeImmutable('2026-08-29T12:00:00+00:00'),
        );
        DB::table('finance_invoices')->where('id', $invoice->value)->update([
            'allocated_minor' => 11_900,
            'open_minor' => 0,
        ]);
        $transport = new DunningTransport;

        (new SendInvoiceDeliveryJob((int) $owner->id, $delivery->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));

        $this->assertSame(0, $transport->calls);
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $delivery->value,
            'status' => 'failed',
            'attempts' => 0,
            'last_error_code' => 'invoice_not_overdue',
        ]);
        $this->assertDatabaseMissing('finance_document_activities', [
            'type' => 'invoice.reminder.sent',
        ]);
    }

    public function test_repository_rechecks_reminder_eligibility_inside_queue_transaction(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update(['timezone' => 'UTC']);
        $invoice = $this->invoice($owner, 'sent', '2026-08-01', 11_900, 11_900, '000000000039');

        try {
            app(InvoiceRepository::class)->queueDelivery(
                $invoice,
                'reminder',
                'customer@example.test',
                new IdempotencyKey('repo-locked-reminder'),
                ['level' => 1],
                new DateTimeImmutable('2026-08-29T12:00:00+00:00'),
            );
            $this->fail('A paid invoice was queued directly through the repository.');
        } catch (\DomainException $exception) {
            $this->assertSame('invoice_not_overdue', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_invoice_deliveries', 0);
    }

    public function test_reminder_retry_preserves_level_and_appends_complete_history(): void
    {
        Storage::fake('invoice-dunning-pdfs');
        config()->set('files.disk', 'invoice-dunning-pdfs');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update([
            'timezone' => 'UTC',
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.com',
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
            'company_smtp_from_address' => 'billing@example.com',
        ]);
        $invoice = $this->invoice($owner, 'sent', '2026-08-01', 11_900, 0, '000000000040');
        app()->instance(InvoiceMailer::class, new DunningRecordingMailer);
        $source = app(QueueInvoiceReminder::class)->handle(
            $invoice,
            2,
            null,
            new DateTimeImmutable('2026-08-29T12:00:00+00:00'),
        );
        DB::table('finance_invoice_deliveries')->where('id', $source->value)->update([
            'status' => 'unknown', 'attempts' => 1, 'last_error_code' => 'delivery_outcome_uncertain',
        ]);

        $retry = app(RetryInvoiceDelivery::class)->handle(
            $source,
            new IdempotencyKey('retry-reminder-level-two'),
        );

        $queued = DB::table('finance_document_activities')
            ->where('type', 'invoice.reminder.queued')
            ->get()
            ->filter(static function (object $activity) use ($retry): bool {
                $encoded = $activity->payload;
                if (! is_string($encoded)) {
                    return false;
                }
                $payload = json_decode($encoded, true);

                return is_array($payload) && ($payload['delivery_id'] ?? null) === $retry->value;
            });
        $this->assertCount(1, $queued);
        $queuedPayload = $queued->first()?->payload;
        $this->assertIsString($queuedPayload);
        $this->assertStringContainsString('"level":2', $queuedPayload);
        $transport = new DunningTransport;
        (new SendInvoiceDeliveryJob((int) $owner->id, $retry->value))
            ->handle(new CompanyInvoiceMailer(new CompanySmtpMailer($transport)));
        $this->assertSame(1, $transport->calls);
        $sent = DB::table('finance_document_activities')
            ->where('type', 'invoice.reminder.sent')
            ->get();
        $this->assertCount(1, $sent);
        $sentPayload = $sent->first()?->payload;
        $this->assertIsString($sentPayload);
        $this->assertStringContainsString('"level":2', $sentPayload);
    }

    private function invoice(
        User $owner,
        string $status,
        string $dueDate,
        int $grossMinor,
        int $allocatedMinor,
        string $suffix,
    ): InvoiceId {
        $now = now();
        $uuid = '018f4ca3-224d-7d8d-9f00-'.$suffix;
        $bytes = '%PDF-dunning-'.$suffix;
        $sha256 = hash('sha256', $bytes);
        $path = 'finance/revisions/'.substr($sha256, 0, 2).'/'.$sha256.'.pdf';
        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id, 'uuid' => $uuid, 'document_type' => 'invoice',
            'status' => $status, 'created_by' => $owner->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $revisionId = DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 1,
            'status' => 'published', 'snapshot' => json_encode([
                'document_number' => 'RE-'.$suffix,
                'customer' => ['name' => 'Customer', 'email' => 'customer@example.test'],
                'due_date' => $dueDate,
            ], JSON_THROW_ON_ERROR),
            'net_minor' => $grossMinor, 'vat_minor' => 0, 'gross_minor' => $grossMinor,
            'currency' => 'EUR', 'pdf_path' => $path, 'pdf_sha256' => $sha256,
            'published_at' => $now, 'created_by' => $owner->id, 'created_at' => $now,
        ]);
        $id = DB::table('finance_invoices')->insertGetId([
            'user_id' => $owner->id, 'uuid' => $uuid, 'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId, 'kind' => 'invoice', 'number' => 'RE-'.$suffix,
            'year' => 2026, 'sequence' => (int) substr($suffix, -6), 'issue_date' => '2026-01-01',
            'due_date' => $dueDate, 'workflow_status' => $status,
            'finalized_at' => $now, 'sent_at' => $status === 'sent' ? $now : null,
            'allocated_minor' => $allocatedMinor, 'open_minor' => $grossMinor - $allocatedMinor,
            'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        Storage::disk((string) config('files.disk', 'files'))->put($path, $bytes);

        return new InvoiceId($id);
    }
}

final class DunningRecordingMailer implements InvoiceMailer
{
    public bool $configured = true;

    public function assertConfigured(int $ownerId): void
    {
        if (! $this->configured) {
            throw new \DomainException('delivery_smtp_unavailable');
        }
    }

    public function assertDocumentReady(string $path, string $sha256): void {}

    public function dispatch(int $ownerId, DeliveryId $deliveryId): void {}
}

final class DunningTransport implements CompanyMailTransport
{
    public int $calls = 0;

    public ?InvoiceRevisionMail $mail = null;

    public function send(string $mailerName, string $recipient, Mailable $mail): CompanyMailTransportResult
    {
        $this->calls++;
        if (! $mail instanceof InvoiceRevisionMail) {
            throw new \LogicException('Expected an invoice revision mail.');
        }
        $this->mail = $mail;

        return CompanyMailTransportResult::accepted();
    }
}
