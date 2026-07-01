<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Invoicing\ZugferdInvoiceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function finalizedInvoice(string $language = 'de'): Invoice
    {
        $customer = Customer::factory()->create(['email' => 'billing@acme.test']);
        $this->post(route('finance.invoices.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-06-01',
            'language' => $language,
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 2, 'unit' => 'h', 'unit_price' => 90, 'tax_rate' => 19],
            ],
        ]);
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->post(route('finance.invoices.finalize', $invoice));

        return $invoice->fresh();
    }

    public function test_draft_pdf_streams(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $this->post(route('finance.invoices.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-06-01',
            'language' => 'en',
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 19]],
        ]);
        $invoice = Invoice::latest('id')->firstOrFail();

        $response = $this->get(route('finance.invoices.pdf', $invoice));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->content());
    }

    public function test_finalized_pdf_is_factur_x(): void
    {
        $this->signIn();
        $invoice = $this->finalizedInvoice();

        $response = $this->get(route('finance.invoices.pdf', $invoice));
        $response->assertOk();
        $body = $response->content();

        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString('factur-x.xml', $body);
    }

    public function test_zugferd_xml_contains_the_invoice_data(): void
    {
        $this->signIn();
        CompanyProfile::current()->update(['legal_name' => 'Kiefer Networks', 'vat_id' => 'DE123456789']);
        $invoice = $this->finalizedInvoice();

        $xml = app(ZugferdInvoiceBuilder::class)
            ->build($invoice->load('lines', 'customer'), CompanyProfile::current())
            ->getContent();

        $this->assertStringContainsString('CrossIndustryInvoice', $xml);
        $this->assertStringContainsString($invoice->number, $xml);
        $this->assertStringContainsString('Kiefer Networks', $xml);
    }

    public function test_pdf_view_renders_in_the_invoice_language(): void
    {
        $this->signIn();
        $invoice = $this->finalizedInvoice('de')->load('lines', 'customer');
        $company = CompanyProfile::current();

        App::setLocale('de');
        $de = View::make('invoices.pdf', ['invoice' => $invoice, 'company' => $company])->render();
        App::setLocale('en');
        $en = View::make('invoices.pdf', ['invoice' => $invoice, 'company' => $company])->render();
        App::setLocale('en');

        $this->assertStringContainsString('Rechnung', $de);
        $this->assertStringContainsString('Invoice', $en);
    }

    public function test_finalized_invoice_can_be_emailed(): void
    {
        Mail::fake();
        $this->signIn();
        $invoice = $this->finalizedInvoice();

        $this->post(route('finance.invoices.email', $invoice))
            ->assertRedirect(route('finance.invoices.show', $invoice));

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail): bool => $mail->hasTo('billing@acme.test'));
    }

    public function test_draft_cannot_be_emailed(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $this->post(route('finance.invoices.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-06-01',
            'language' => 'en',
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 19]],
        ]);
        $invoice = Invoice::latest('id')->firstOrFail();

        $this->post(route('finance.invoices.email', $invoice))->assertForbidden();
    }
}
