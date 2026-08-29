<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Invoices\QueueInvoiceReminder;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Queries\InvoiceAgingQuery;
use App\Modules\Finance\Infrastructure\Mail\CompanyInvoiceMailer;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransport;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransportResult;
use App\Modules\Finance\Infrastructure\Mail\CompanySmtpMailer;
use App\Modules\Finance\Infrastructure\Mail\InvoiceRevisionMail;
use App\Modules\Finance\Infrastructure\Scheduling\SendInvoiceDeliveryJob;
use DateTimeImmutable;
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
        $this->assertSame(28, $transport->mail?->daysOverdue);
        $this->assertSame('119.00 EUR', $transport->mail?->openAmount);
        $activities = DB::table('finance_document_activities')
            ->where('type', 'invoice.reminder.sent')
            ->get();
        $this->assertCount(1, $activities);
        $this->assertStringContainsString('"level":1', (string) $activities->first()?->payload);
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
    public function assertConfigured(int $ownerId): void {}

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
