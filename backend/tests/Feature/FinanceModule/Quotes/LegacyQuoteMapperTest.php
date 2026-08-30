<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\FinanceProject;
use App\Models\FinanceQuote;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyQuoteDiagnostic;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyQuoteMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyQuoteMapperTest extends TestCase
{
    use RefreshDatabase;

    private LegacyQuoteMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new LegacyQuoteMapper(new DocumentCalculator);
    }

    public function test_an_unnumbered_draft_maps_to_a_mutable_draft(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft']);

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertSame('legacy.finance_quote', $mapped['source_type']);
        $this->assertSame($quote->id, $mapped['source_id']);
        $this->assertSame($owner->id, $mapped['owner_id']);
        $this->assertSame('draft', $mapped['kind']);
        $this->assertSame('draft', $mapped['status']);
        $this->assertArrayNotHasKey('revision', $mapped);
        $this->assertSame(20000, $mapped['draft']['net_minor']);
        $this->assertSame(3800, $mapped['draft']['vat_minor']);
        $this->assertSame(23800, $mapped['draft']['gross_minor']);
        $this->assertSame('Baseline quote', $mapped['draft']['payload']['title']);
        $this->assertSame('Internal note', $mapped['draft']['payload']['internal_note']);
        $this->assertArrayNotHasKey('customer_note', $mapped['draft']['payload']);
    }

    public function test_a_numbered_sent_quote_maps_to_one_published_revision(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $bytes = "%PDF-1.7\n%mock quote pdf\n";
        Storage::disk((string) config('files.disk'))->put('invoices/quote-42.pdf', $bytes);
        $quote = $this->quote([
            'status' => 'sent',
        ], numbered: true, pdfPath: 'invoices/quote-42.pdf');

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertSame('published', $mapped['kind']);
        $this->assertSame('sent', $mapped['status']);
        $this->assertArrayNotHasKey('draft', $mapped);
        $revision = $mapped['revision'];
        $this->assertSame('AN-2026-0007', $revision['number']);
        $this->assertSame(2026, $revision['sequence_year']);
        $this->assertSame(7, $revision['sequence_number']);
        $this->assertSame('invoices/quote-42.pdf', $revision['pdf_path']);
        $this->assertSame(hash('sha256', $bytes), $revision['pdf_sha256']);
        $this->assertSame('AN-2026-0007', $revision['snapshot']['document_number']);
        $this->assertSame(1, $revision['snapshot']['revision_number']);
        $this->assertSame('AN-2026-0007', $revision['snapshot']['revision_label']);
        $this->assertNull($revision['snapshot']['series_uuid']);
        $this->assertSame('Internal note', $revision['snapshot']['customer_note']);
        $this->assertSame(20000, $revision['net_minor']);
        $this->assertSame(3800, $revision['vat_minor']);
        $this->assertSame(23800, $revision['gross_minor']);
    }

    public function test_an_accepted_quote_preserves_its_decision_timestamp(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Storage::disk((string) config('files.disk'))->put('invoices/quote-accepted.pdf', "%PDF-1.7\nx\n");
        $quote = $this->quote(['status' => 'accepted'], numbered: true, pdfPath: 'invoices/quote-accepted.pdf');
        $quote->forceFill(['accepted_at' => '2026-08-15 10:00:00'])->save();
        $quote = $quote->fresh();

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertSame('accepted', $mapped['status']);
        $this->assertNotNull($mapped['accepted_at']);
        $this->assertNull($mapped['declined_at']);
    }

    public function test_a_declined_quote_preserves_its_decision_timestamp(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Storage::disk((string) config('files.disk'))->put('invoices/quote-declined.pdf', "%PDF-1.7\nx\n");
        $quote = $this->quote(['status' => 'declined'], numbered: true, pdfPath: 'invoices/quote-declined.pdf');
        $quote->forceFill(['declined_at' => '2026-08-16 10:00:00'])->save();
        $quote = $quote->fresh();

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertSame('declined', $mapped['status']);
        $this->assertNotNull($mapped['declined_at']);
        $this->assertNull($mapped['accepted_at']);
    }

    public function test_an_expired_by_date_quote_maps_without_a_stored_expired_status(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Storage::disk((string) config('files.disk'))->put('invoices/quote-expired.pdf', "%PDF-1.7\nx\n");
        $quote = $this->quote(['status' => 'sent', 'valid_until' => '2020-01-01'], numbered: true, pdfPath: 'invoices/quote-expired.pdf');

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertSame('sent', $mapped['status']);
        $this->assertSame('2020-01-01', $mapped['revision']['snapshot']['valid_until']);
    }

    public function test_a_soft_deleted_quote_maps_with_its_deleted_at_timestamp(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft']);
        $quote->delete();
        $quote = FinanceQuote::withTrashed()->findOrFail($quote->id);

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertNotNull($mapped['deleted_at']);
    }

    public function test_converted_invoice_and_project_references_become_unresolved_external_references(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = new Invoice;
        $invoice->forceFill([
            'user_id' => $owner->id,
            'status' => 'draft',
            'type' => 'invoice',
            'currency' => 'EUR',
            'customer' => ['name' => 'Ada GmbH'],
            'lines' => [],
            'net' => '0.00',
            'vat' => '0.00',
            'gross' => '0.00',
            'version' => 0,
            'version_seq' => 0,
        ])->save();
        $project = new FinanceProject;
        $project->fill(['name' => 'Converted project', 'kind' => 'business'])->save();
        $quote = $this->quote(['status' => 'accepted']);
        $quote->forceFill(['converted_invoice_id' => $invoice->id, 'converted_project_id' => $project->id])->save();
        $quote = $quote->fresh();

        $mapped = $this->mapper->map($quote);

        $this->assertIsArray($mapped);
        $this->assertSame([
            ['target_type' => 'invoice', 'target_reference' => 'legacy-invoice:'.$invoice->id, 'target_id' => null, 'resolved' => false],
            ['target_type' => 'project', 'target_reference' => 'legacy-project:'.$project->id, 'target_id' => null, 'resolved' => false],
        ], $mapped['conversions']);
    }

    public function test_a_missing_pdf_file_is_a_blocking_diagnostic(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'sent'], numbered: true, pdfPath: 'invoices/does-not-exist.pdf');

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::MISSING_PDF, $mapped->code);
    }

    public function test_an_unsafe_pdf_path_is_a_blocking_diagnostic(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'sent'], numbered: true, pdfPath: '../etc/passwd');

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::INVALID_PDF_PATH, $mapped->code);
    }

    public function test_pdf_bytes_that_are_not_a_pdf_are_a_blocking_diagnostic(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Storage::disk((string) config('files.disk'))->put('invoices/not-a-pdf.pdf', 'plain text, not a pdf');
        $quote = $this->quote(['status' => 'sent'], numbered: true, pdfPath: 'invoices/not-a-pdf.pdf');

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::INVALID_PDF_MIME, $mapped->code);
    }

    public function test_a_foreign_partner_reference_is_a_blocking_diagnostic(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $this->actingAs($stranger);
        $foreignPartner = new FinancePartner;
        $foreignPartner->fill(['name' => 'Someone Else GmbH'])->save();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft', 'partner_id' => $foreignPartner->id]);

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::FOREIGN_PARTNER, $mapped->code);
    }

    public function test_a_foreign_product_reference_is_a_blocking_diagnostic(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $this->actingAs($stranger);
        $foreignProduct = new FinanceProduct;
        $foreignProduct->fill([
            'kind' => 'service', 'name' => 'Consulting', 'unit' => 'hour', 'price_net' => '100.00', 'vat_rate' => '19.00',
        ])->save();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft'], productId: $foreignProduct->id);

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::FOREIGN_PRODUCT, $mapped->code);
    }

    public function test_an_unsupported_numeric_scale_is_a_blocking_diagnostic(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft']);
        $lines = $quote->lines;
        $lines[0]['qty'] = '2.00001';
        $quote->forceFill(['lines' => $lines])->save();

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::UNSUPPORTED_NUMERIC_SCALE, $mapped->code);
    }

    public function test_an_unknown_currency_is_a_blocking_diagnostic(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft', 'currency' => 'EURO']);

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::UNKNOWN_CURRENCY, $mapped->code);
    }

    public function test_a_server_total_mismatch_is_a_blocking_diagnostic(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $quote = $this->quote(['status' => 'draft', 'gross' => '999.99']);

        $mapped = $this->mapper->map($quote);

        $this->assertInstanceOf(LegacyQuoteDiagnostic::class, $mapped);
        $this->assertSame(LegacyQuoteDiagnostic::SERVER_TOTAL_MISMATCH, $mapped->code);
    }

    public function test_mapping_the_same_legacy_quote_twice_is_deterministic(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Storage::disk((string) config('files.disk'))->put('invoices/quote-repeat.pdf', "%PDF-1.7\nx\n");
        $quote = $this->quote(['status' => 'sent'], numbered: true, pdfPath: 'invoices/quote-repeat.pdf');

        $first = $this->mapper->map($quote->fresh());
        $second = $this->mapper->map($quote->fresh());

        $this->assertSame($first, $second);
    }

    /** @param  array<string, mixed>  $attrs */
    private function quote(array $attrs = [], bool $numbered = false, ?string $pdfPath = null, ?int $productId = null): FinanceQuote
    {
        $quote = new FinanceQuote;
        $quote->fill(array_merge([
            'title' => 'Baseline quote',
            'status' => 'draft',
            'customer' => ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            'issue_date' => '2026-08-01',
            'valid_until' => '2026-08-31',
            'currency' => 'EUR',
            'lines' => [[
                'desc' => 'Consulting',
                'qty' => '2.0000',
                'unit' => 'hour',
                'unitPrice' => '100.00',
                'vatRate' => '19.00',
                'kind' => 'service',
                'productId' => $productId,
            ]],
            'discount_type' => null,
            'discount_value' => null,
            'net' => '200.00',
            'vat' => '38.00',
            'gross' => '238.00',
            'intro_text' => null,
            'outro_text' => null,
            'note' => 'Internal note',
        ], $attrs));
        $quote->save();

        if ($numbered) {
            $quote->forceFill([
                'number' => 'AN-2026-0007',
                'year' => 2026,
                'seq' => 7,
                'pdf_path' => $pdfPath,
                'sent_at' => '2026-08-02 09:00:00',
            ])->save();
        }

        return $quote->fresh();
    }
}
