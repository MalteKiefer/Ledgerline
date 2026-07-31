<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\FinancePartner;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A business partner may carry a dedicated invoice email (Rechnungs-E-Mail),
 * separate from the general email. It is stored/returned via the partner CRUD,
 * validated, and — when an invoice's customer snapshot carries `invoiceEmail` —
 * used as the recipient when emailing the invoice, falling back to the general
 * customer email when absent.
 */
class PartnerInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        Mail::fake();
    }

    private function configureSmtp(): void
    {
        UserSetting::for((int) auth()->id())->update([
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.com',
            'company_smtp_from_address' => 'me@example.com',
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
        ]);
    }

    private function invoiceWithPdf(User $user, array $customer): Invoice
    {
        $this->actingAs($user);
        $inv = Invoice::create([
            'number' => '1', 'seq' => 1, 'year' => 2026, 'status' => 'sent',
            'issue_date' => '2026-03-15', 'imported' => false, 'currency' => 'EUR',
            'gross' => 119, 'net' => 100, 'vat' => 19,
            'customer' => $customer,
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
        ]);
        $path = 'invoices/'.Str::uuid()->toString();
        Storage::disk(config('files.disk'))->put($path, '%PDF-1.4 fake');
        $inv->forceFill(['pdf_path' => $path])->saveQuietly();

        return $inv;
    }

    public function test_partner_stores_and_returns_invoice_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('finance.partners.store'), [
            'name' => 'ACME GmbH',
            'email' => 'hello@acme.example',
            'invoice_email' => 'billing@acme.example',
        ])
            ->assertCreated()
            ->assertJsonPath('partner.invoice_email', 'billing@acme.example');

        $partner = FinancePartner::query()->firstOrFail();
        $this->assertSame('billing@acme.example', $partner->invoice_email);
    }

    public function test_invalid_invoice_email_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('finance.partners.store'), [
            'name' => 'ACME GmbH',
            'invoice_email' => 'not-an-email',
        ])->assertInvalid(['invoice_email']);
    }

    public function test_email_prefers_customer_invoice_email(): void
    {
        $user = User::factory()->create();
        $inv = $this->invoiceWithPdf($user, [
            'name' => 'ACME',
            'email' => 'general@example.com',
            'invoiceEmail' => 'billing@example.com',
        ]);
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))->assertOk();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $m): bool => $m->hasTo('billing@example.com'));
    }

    public function test_email_falls_back_to_customer_email_when_no_invoice_email(): void
    {
        $user = User::factory()->create();
        $inv = $this->invoiceWithPdf($user, [
            'name' => 'ACME',
            'email' => 'general@example.com',
        ]);
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))->assertOk();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $m): bool => $m->hasTo('general@example.com'));
    }
}
