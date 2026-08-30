<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\BankTransaction;
use App\Models\Invoice as LegacyInvoiceModel;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Infrastructure\Compatibility\InvoiceControlTotals;
use App\Modules\Finance\Infrastructure\Compatibility\InvoiceCutoverCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyInvoiceMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        // The finance-v2 immutable-revision DocumentStorage binding resolves
        // its own (S3) disk independent of config('files.disk'); this test
        // only needs putPdf() to accept the copied legacy bytes and produce
        // the sha256-sharded path importFinalized()/assertDeliveryReady()
        // expect, not real S3 credentials.
        app()->instance(DocumentStorage::class, new class implements DocumentStorage
        {
            /** @var array<string, string> */
            public array $written = [];

            public function putPdf(string $seriesUuid, string $bytes, DocumentStorageWrite $write): StoredDocument
            {
                $sha256 = hash('sha256', $bytes);
                $path = 'finance/revisions/'.substr($sha256, 0, 2).'/'.$sha256.'.pdf';
                $this->written[$path] = $bytes;
                Storage::disk(config('files.disk'))->put($path, $bytes);

                return new StoredDocument($path, $sha256);
            }

            public function delete(DocumentStorageWrite $write): void {}
        });
    }

    public function test_a_draft_legacy_invoice_migrates_to_an_editable_draft_without_a_number(): void
    {
        $owner = User::factory()->create();
        $this->legacyInvoice($owner, ['status' => 'draft', 'number' => null]);

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $row = DB::table('finance_invoices')->where('user_id', $owner->id)->first();
        $this->assertNull($row->number);
        $this->assertSame('draft', $row->workflow_status);
        $this->assertSame('legacy_invoice', $row->source_type);
    }

    public function test_a_finalized_and_sent_legacy_invoice_preserves_its_exact_number_and_pdf_bytes(): void
    {
        $owner = User::factory()->create();
        $pdfBytes = '%PDF-1.4 legacy invoice bytes';
        $legacy = $this->legacyInvoice($owner, [
            'status' => 'sent',
            'number' => '2026-0007',
            'year' => 2026,
            'seq' => 7,
            'pdf_path' => 'invoices/legacy-0007.pdf',
        ]);
        Storage::disk(config('files.disk'))->put('invoices/legacy-0007.pdf', $pdfBytes);

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $row = DB::table('finance_invoices')
            ->where('user_id', $owner->id)
            ->where('source_key', (string) $legacy->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-0007', $row->number);
        $this->assertSame(2026, $row->year);
        $this->assertSame(7, $row->sequence);
        $this->assertSame('sent', $row->workflow_status);
        $this->assertNotNull($row->sent_at);

        $revision = DB::table('finance_document_revisions')->where('id', $row->current_revision_id)->first();
        $this->assertSame('published', $revision->status);
        $this->assertSame(hash('sha256', $pdfBytes), $revision->pdf_sha256);
        $disk = Storage::disk(config('files.disk'));
        $this->assertSame($pdfBytes, $disk->get($revision->pdf_path));

        // The live allocator must never re-issue an already-migrated number.
        $sequence = DB::table('finance_invoice_sequences')
            ->where('user_id', $owner->id)->where('series_key', 'invoice')->where('year', 2026)->first();
        $this->assertSame(8, $sequence->next_sequence);
    }

    public function test_a_full_bank_payment_link_settles_the_invoice_and_a_partial_link_leaves_it_partially_paid(): void
    {
        $owner = User::factory()->create();
        Storage::disk(config('files.disk'))->put('invoices/full.pdf', '%PDF-full');
        Storage::disk(config('files.disk'))->put('invoices/partial.pdf', '%PDF-partial');
        $full = $this->legacyInvoice($owner, [
            'status' => 'sent', 'number' => '2026-0001', 'year' => 2026, 'seq' => 1,
            'pdf_path' => 'invoices/full.pdf', 'net' => 100, 'vat' => 19, 'gross' => 119,
        ]);
        $partial = $this->legacyInvoice($owner, [
            'status' => 'sent', 'number' => '2026-0002', 'year' => 2026, 'seq' => 2,
            'pdf_path' => 'invoices/partial.pdf', 'net' => 200, 'vat' => 38, 'gross' => 238,
            'lines' => [[
                'desc' => 'Service', 'qty' => '2.0000', 'unit' => 'hour',
                'unitPrice' => '100.00', 'vatRate' => '19.00', 'kind' => 'service', 'productId' => null,
            ]],
        ]);
        $this->bankTransaction($owner, $full, '119.00');
        $this->bankTransaction($owner, $partial, '100.00');

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $fullRow = DB::table('finance_invoices')->where('source_key', (string) $full->id)->first();
        $partialRow = DB::table('finance_invoices')->where('source_key', (string) $partial->id)->first();
        $this->assertSame(0, (int) $fullRow->open_minor);
        $this->assertSame(11900, (int) $fullRow->allocated_minor);
        $this->assertSame(13800, (int) $partialRow->open_minor);
        $this->assertSame(10000, (int) $partialRow->allocated_minor);
        $this->assertSame(2, DB::table('finance_payments')->where('user_id', $owner->id)->count());
    }

    public function test_a_paid_legacy_invoice_without_enough_linked_money_gets_one_flagged_residual_payment(): void
    {
        $owner = User::factory()->create();
        Storage::disk(config('files.disk'))->put('invoices/paid.pdf', '%PDF-paid');
        $legacy = $this->legacyInvoice($owner, [
            'status' => 'paid', 'number' => '2026-0003', 'year' => 2026, 'seq' => 3,
            'pdf_path' => 'invoices/paid.pdf', 'net' => 100, 'vat' => 19, 'gross' => 119,
            'paid_at' => '2026-06-01 00:00:00',
        ]);

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $row = DB::table('finance_invoices')->where('source_key', (string) $legacy->id)->first();
        $this->assertSame(0, (int) $row->open_minor);
        $payment = DB::table('finance_payments')
            ->where('user_id', $owner->id)
            ->where('source_type', 'legacy_invoice_paid_marker')
            ->first();
        $this->assertNotNull($payment);
        $this->assertSame((string) $legacy->id, $payment->source_key);
        $this->assertSame(11900, (int) $payment->amount_minor);
    }

    public function test_a_cancellation_pair_migrates_the_original_first_and_links_cancels_invoice_id(): void
    {
        $owner = User::factory()->create();
        Storage::disk(config('files.disk'))->put('invoices/orig.pdf', '%PDF-orig');
        Storage::disk(config('files.disk'))->put('invoices/credit.pdf', '%PDF-credit');
        $original = $this->legacyInvoice($owner, [
            'status' => 'sent', 'number' => '2026-0010', 'year' => 2026, 'seq' => 10,
            'pdf_path' => 'invoices/orig.pdf', 'net' => 100, 'vat' => 19, 'gross' => 119,
        ]);
        $credit = $this->legacyInvoice($owner, [
            'status' => 'sent', 'number' => '2026-0011', 'year' => 2026, 'seq' => 11,
            'pdf_path' => 'invoices/credit.pdf', 'net' => -100, 'vat' => -19, 'gross' => -119,
            'cancels_invoice_id' => $original->id,
            'lines' => [[
                'desc' => 'Service', 'qty' => '-1.0000', 'unit' => 'hour',
                'unitPrice' => '100.00', 'vatRate' => '19.00', 'kind' => 'service', 'productId' => null,
            ]],
        ]);

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $originalRow = DB::table('finance_invoices')->where('source_key', (string) $original->id)->first();
        $creditRow = DB::table('finance_invoices')->where('source_key', (string) $credit->id)->first();
        $this->assertNotNull($originalRow);
        $this->assertNotNull($creditRow);
        $this->assertSame($originalRow->id, $creditRow->cancels_invoice_id);
        $this->assertSame(-11900, (int) DB::table('finance_document_revisions')
            ->where('id', $creditRow->current_revision_id)->value('gross_minor'));
    }

    public function test_running_the_migration_command_twice_creates_no_duplicates(): void
    {
        $owner = User::factory()->create();
        Storage::disk(config('files.disk'))->put('invoices/x.pdf', '%PDF-x');
        $this->legacyInvoice($owner, [
            'status' => 'sent', 'number' => '2026-0099', 'year' => 2026, 'seq' => 99,
            'pdf_path' => 'invoices/x.pdf',
        ]);
        $this->legacyInvoice($owner, ['status' => 'draft', 'number' => null]);

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);
        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $this->assertSame(2, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(2, DB::table('finance_document_series')->where('user_id', $owner->id)->count());
    }

    public function test_control_totals_and_the_cutover_gate_report_ready_after_a_complete_migration(): void
    {
        $owner = User::factory()->create();
        Storage::disk(config('files.disk'))->put('invoices/a.pdf', '%PDF-a');
        $this->legacyInvoice($owner, [
            'status' => 'sent', 'number' => '2026-0005', 'year' => 2026, 'seq' => 5,
            'pdf_path' => 'invoices/a.pdf', 'net' => 100, 'vat' => 19, 'gross' => 119,
        ]);

        $totals = app(InvoiceControlTotals::class);
        $before = $totals->compare((int) $owner->id);
        $this->assertFalse($before['ok']);

        $this->artisan('finance:migrate-invoice-slice', ['--user' => [$owner->id]])->assertExitCode(0);

        $after = $totals->compare((int) $owner->id);
        $this->assertTrue($after['ok'], implode('; ', $after['mismatches']));

        $check = app(InvoiceCutoverCheck::class);
        $this->assertTrue($check->run()['ready']);

        $this->artisan('finance:check-invoice-cutover')->assertExitCode(0);
    }

    /** @param array<string, mixed> $attrs */
    private function legacyInvoice(User $owner, array $attrs = []): LegacyInvoiceModel
    {
        $invoice = new LegacyInvoiceModel;
        $invoice->forceFill(array_merge([
            'user_id' => $owner->id,
            'status' => 'draft',
            'type' => 'invoice',
            'issue_date' => '2026-05-04',
            'due_date' => '2026-05-18',
            'currency' => 'EUR',
            'customer' => ['name' => 'ACME'],
            'lines' => [[
                'desc' => 'Service', 'qty' => '1.0000', 'unit' => 'hour',
                'unitPrice' => '100.00', 'vatRate' => '19.00', 'kind' => 'service', 'productId' => null,
            ]],
            'net' => '100.00',
            'vat' => '19.00',
            'gross' => '119.00',
            'number' => null,
            'year' => null,
            'seq' => null,
        ], $attrs));
        $invoice->save();

        return $invoice;
    }

    private function bankTransaction(User $owner, LegacyInvoiceModel $invoice, string $amount): BankTransaction
    {
        $method = new PaymentMethod;
        $method->forceFill(['user_id' => $owner->id]);
        $method->fill(['type' => 'bank', 'name' => 'Test account', 'scope' => 'business', 'business' => true]);
        $method->save();

        $transaction = new BankTransaction;
        $transaction->forceFill([
            'user_id' => $owner->id,
            'payment_method_id' => $method->id,
            'date' => '2026-06-01',
            'amount' => $amount,
            'invoice_id' => $invoice->id,
        ]);
        $transaction->save();

        return $transaction;
    }
}
