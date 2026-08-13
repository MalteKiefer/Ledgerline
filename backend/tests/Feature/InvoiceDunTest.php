<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sending a customer-facing payment reminder (Mahnung) for an overdue invoice:
 * the happy path sends over the company SMTP, increments reminder_count, stamps
 * reminded_at + audits; each precondition (not overdue / no PDF / no recipient /
 * SMTP unconfigured) yields a 422. Owner-scoped (404 across users).
 */
class InvoiceDunTest extends TestCase
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
            'issue_date' => '2026-01-01', 'due_date' => '2026-01-15', 'imported' => false, 'currency' => 'EUR',
            'gross' => 119, 'net' => 100, 'vat' => 19,
            'customer' => ['name' => 'ACME', 'email' => 'client@example.com'],
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
        ], $attrs));
    }

    public function test_dun_sends_customer_mail_increments_level_and_audits(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.dun', $inv->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('level', 1)
            ->assertJsonStructure(['ok', 'level', 'reminded_at']);

        Mail::assertSent(InvoiceReminderMail::class, fn (InvoiceReminderMail $m): bool => $m->hasTo('client@example.com') && $m->level === 1);
        $fresh = $inv->fresh();
        $this->assertNotNull($fresh->reminded_at);
        $this->assertSame(1, (int) $fresh->reminder_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.dunned']);

        // A second reminder bumps the level to 2 (Mahnstufe).
        $this->postJson(route('finance.invoices.dun', $inv->id))->assertOk()->assertJsonPath('level', 2);
    }

    public function test_explicit_recipient_overrides_customer_email(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.dun', $inv->id), ['to' => 'other@example.com'])->assertOk();
        Mail::assertSent(InvoiceReminderMail::class, fn (InvoiceReminderMail $m): bool => $m->hasTo('other@example.com'));
    }

    public function test_422_when_not_overdue(): void
    {
        $user = User::factory()->create();
        // due_date in the future → not overdue.
        $inv = $this->withPdf($this->invoice($user, ['due_date' => '2999-01-01']));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.dun', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'not_overdue');
        Mail::assertNothingSent();
        $this->assertNull($inv->fresh()->reminded_at);
    }

    public function test_422_when_paid_is_not_overdue(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user, ['status' => 'paid']));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.dun', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'not_overdue');
        Mail::assertNothingSent();
    }

    public function test_422_when_no_pdf(): void
    {
        $user = User::factory()->create();
        $inv = $this->invoice($user); // no pdf
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.dun', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'no_pdf');
        Mail::assertNothingSent();
    }

    public function test_422_when_no_recipient(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user, ['customer' => ['name' => 'ACME']]));
        $this->configureSmtp();

        $this->postJson(route('finance.invoices.dun', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'no_recipient');
        Mail::assertNothingSent();
    }

    public function test_422_when_smtp_unconfigured(): void
    {
        $user = User::factory()->create();
        $inv = $this->withPdf($this->invoice($user)); // SMTP left unconfigured

        $this->postJson(route('finance.invoices.dun', $inv->id))
            ->assertStatus(422)->assertJsonPath('error', 'no_smtp');
        Mail::assertNothingSent();
    }

    public function test_owner_scoped_404(): void
    {
        $owner = User::factory()->create();
        $inv = $this->withPdf($this->invoice($owner));
        $this->configureSmtp();

        $this->actingAs(User::factory()->create());
        $this->postJson(route('finance.invoices.dun', $inv->id))->assertNotFound();
        Mail::assertNothingSent();
    }
}
