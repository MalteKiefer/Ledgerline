<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Emailing a finalized invoice's stored PDF to the customer: happy path stamps
 * sent_at + audits + actually sends, and each precondition (no PDF / no
 * recipient / SMTP unconfigured / not finalized) yields a 422. Route model
 * binding stays owner-scoped (404 across users).
 */
class InvoiceEmailTest extends TestCase
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
        // Invoices send over the acting user's OWN company SMTP, not the
        // workspace notification SMTP.
        UserSetting::for((int) auth()->id())->update([
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.com',
            'company_smtp_from_address' => 'me@example.com',
            'company_smtp_port' => 587,
            'company_smtp_encryption' => 'tls',
        ]);
    }

    private function withPdf(Invoice $invoice): Invoice
    {
        $path = 'invoices/'.Str::uuid()->toString();
        Storage::disk(config('files.disk'))->put($path, '%PDF-1.4 fake');
        $invoice->forceFill(['pdf_path' => $path])->saveQuietly();

        return $invoice;
    }

    private function invoice(User $user, array $attrs = []): Invoice
    {
        $this->actingAs($user);

        return Invoice::create(array_merge([
            'number' => '1', 'seq' => 1, 'year' => 2026, 'status' => 'sent',
            'issue_date' => '2026-03-15', 'imported' => false, 'currency' => 'EUR',
            'gross' => 119, 'net' => 100, 'vat' => 19,
            'customer' => ['name' => 'ACME', 'email' => 'client@example.com'],
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
        ], $attrs));
    }

    public function test_send_stamps_sent_at_audits_and_sends(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'sent_at']);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $m): bool => $m->hasTo('client@example.com'));
        $this->assertNotNull($inv->fresh()->sent_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.emailed']);
    }

    /**
     * Octane-safety: the per-user company SMTP creds must not linger in the merged
     * config after a send, or a persistent worker would carry one finance user's SMTP
     * password (and mailer) into the next request. companyMailer() sets it; the send
     * path must tear it back down.
     */
    public function test_company_mailer_config_is_torn_down_after_send(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))->assertOk();

        $this->assertNull(config('mail.mailers.company_smtp'));
        $this->assertNull(config('mail.from.company_smtp'));
    }

    public function test_explicit_recipient_overrides_customer_email(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id), ['to' => 'other@example.com'])->assertOk();
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $m): bool => $m->hasTo('other@example.com'));
    }

    public function test_422_when_no_pdf(): void
    {
        $user = User::factory()->create();
        $inv = $this->invoice($user); // no pdf_path
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'no_pdf');
        Mail::assertNothingSent();
        $this->assertNull($inv->fresh()->sent_at);
    }

    public function test_422_when_no_recipient(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user, ['customer' => ['name' => 'ACME']])); // no email
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'no_recipient');
        Mail::assertNothingSent();
    }

    public function test_422_when_smtp_unconfigured(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user)); // SMTP left unconfigured

        $this->postJson(route('finance.invoices.email', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'no_smtp');
        Mail::assertNothingSent();
    }

    public function test_422_when_not_finalized(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user, ['number' => null, 'seq' => null, 'status' => 'draft']));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.email', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'not_finalized');
        Mail::assertNothingSent();
    }

    public function test_owner_scoped_404(): void
    {
        $owner = User::factory()->create();
        $inv = $this->withPdf($this->invoice($owner));
        $this->configureSmtp();

        $this->actingAs(User::factory()->create());
        $this->postJson(route('finance.invoices.email', $inv->id))->assertNotFound();
        Mail::assertNothingSent();
    }
}
