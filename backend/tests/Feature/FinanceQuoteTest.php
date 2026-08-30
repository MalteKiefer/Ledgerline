<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\QuoteMail;
use App\Models\FinanceProduct;
use App\Models\FinanceQuote;
use App\Models\FinanceStockMovement;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quote_mixes_services_and_hardware_and_starts_as_a_draft(): void
    {
        $this->signIn();
        $hours = $this->article(['kind' => 'service', 'name' => 'Beratung', 'unit' => 'Stunde', 'price_net' => 120]);
        $switch = $this->article(['kind' => 'hardware', 'name' => 'Switch', 'unit' => 'Stück', 'price_net' => 249.9, 'track_stock' => true]);

        $res = $this->postJson(route('api.finance.quotes.store'), [
            'title' => 'Netzwerk Neubau',
            'customer' => ['name' => 'IntellyTec GmbH'],
            'lines' => [
                ['desc' => 'Beratung', 'qty' => 8, 'unit' => 'Stunde', 'unitPrice' => 120, 'vatRate' => 19, 'kind' => 'service', 'productId' => $hours->id],
                ['desc' => 'Switch', 'qty' => 2, 'unit' => 'Stück', 'unitPrice' => 249.9, 'vatRate' => 19, 'kind' => 'hardware', 'productId' => $switch->id],
            ],
            'net' => 1459.8, 'vat' => 277.36, 'gross' => 1737.16,
        ])->assertCreated();

        $res->assertJsonPath('quote.status', 'draft')
            ->assertJsonPath('quote.number', null)
            ->assertJsonPath('quote.lines.1.kind', 'hardware')
            ->assertJsonPath('quote.lines.1.productId', $switch->id);

        // A validity date is filled in so the usual case needs no thought.
        $this->assertNotNull($res->json('quote.valid_until'));
        $this->getJson(route('api.finance.data'))->assertOk()->assertJsonPath('quotes.0.title', 'Netzwerk Neubau');
    }

    public function test_sending_assigns_a_number_from_the_configured_template(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->forceFill(['quote_number_format' => 'AN-YYYY-NNNN', 'quote_next_number' => 7])->save();

        $quote = $this->quote(['issue_date' => '2026-05-04']);
        $this->postJson(route('api.finance.quotes.send', $quote))
            ->assertOk()
            ->assertJsonPath('quote.number', 'AN-2026-0007')
            ->assertJsonPath('quote.seq', 7)
            ->assertJsonPath('quote.status', 'sent');

        // The next one continues the sequence.
        $second = $this->quote(['issue_date' => '2026-06-01']);
        $this->postJson(route('api.finance.quotes.send', $second))->assertJsonPath('quote.number', 'AN-2026-0008');
    }

    public function test_sending_twice_does_not_mint_a_second_number(): void
    {
        $this->signIn();
        $quote = $this->quote([]);
        $first = $this->postJson(route('api.finance.quotes.send', $quote))->json('quote.number');
        $again = $this->postJson(route('api.finance.quotes.send', $quote))->json('quote.number');

        $this->assertSame($first, $again);
    }

    public function test_a_binned_quote_keeps_its_number_out_of_circulation(): void
    {
        // The customer is holding a PDF with that number on it; a second,
        // different document with the same number would be worse than a gap.
        $this->signIn();
        $quote = $this->quote([]);
        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();
        $number = $quote->fresh()?->number;
        $this->deleteJson(route('api.finance.quotes.destroy', $quote))->assertOk();

        $next = $this->quote([]);
        $this->postJson(route('api.finance.quotes.send', $next))->assertOk();

        $this->assertNotSame($number, $next->fresh()?->number);
    }

    public function test_a_sent_quote_cannot_be_edited_but_can_be_copied(): void
    {
        $this->signIn();
        $quote = $this->quote(['title' => 'Original']);
        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();

        $this->putJson(route('api.finance.quotes.update', $quote), ['title' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'quote_locked');
        $this->assertSame('Original', (string) $quote->fresh()?->title);

        // The way to change it: a fresh draft that takes its own number.
        $copy = $this->postJson(route('api.finance.quotes.duplicate', $quote))->assertCreated();
        $copy->assertJsonPath('quote.status', 'draft')->assertJsonPath('quote.number', null);
        $this->putJson(route('api.finance.quotes.update', $copy->json('quote.id')), ['title' => 'Changed'])->assertOk();
    }

    public function test_converting_copies_the_lines_into_a_draft_invoice_and_is_idempotent(): void
    {
        $this->signIn();
        $switch = $this->article(['kind' => 'hardware', 'track_stock' => true]);
        $quote = $this->quote([
            'lines' => [['desc' => 'Switch', 'qty' => 3, 'unit' => 'Stück', 'unitPrice' => 100, 'vatRate' => 19, 'kind' => 'hardware', 'productId' => $switch->id]],
            'net' => 300, 'vat' => 57, 'gross' => 357,
        ]);
        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();

        $res = $this->postJson(route('api.finance.quotes.convert', $quote))->assertCreated();
        $res->assertJsonPath('invoice.status', 'draft')
            // An invoice takes its own number when finalised; a quote number is
            // not an invoice number.
            ->assertJsonPath('invoice.number', null)
            ->assertJsonPath('invoice.lines.0.productId', $switch->id)
            ->assertJsonPath('invoice.gross', '357.00')
            ->assertJsonPath('quote.status', 'accepted');

        $invoiceId = (int) $res->json('invoice.id');

        // Accepting does not move goods — nothing has been billed yet.
        $this->assertSame(0, FinanceStockMovement::query()->count());

        // A second click reopens the same invoice rather than billing twice.
        $this->postJson(route('api.finance.quotes.convert', $quote))
            ->assertOk()
            ->assertJsonPath('already', true)
            ->assertJsonPath('invoice.id', $invoiceId);

        // Conversion now creates a finance-v2 invoice, not a legacy one.
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(1, DB::table('finance_invoices')->where('source_type', 'legacy_quote_snapshot')->count());
    }

    // Hardware-stock-booking-on-finalize (goods leave the shelf once, idempotently,
    // negative stock recorded not refused) used to be tested here against the
    // now-deleted legacy FinanceController::finalizeInvoice route. It is covered
    // equivalently -- and considerably more thoroughly, including scale-4
    // quantities and owner-scoping -- by
    // tests/Feature/FinanceModule/InvoiceFinalizationTest.php::
    // test_inventory_sale_is_scale_four_owner_scoped_hardware_only_and_idempotent()
    // against the finance-v2 invoice module, which is now the only invoice writer.

    public function test_a_decision_needs_a_sent_quote(): void
    {
        $this->signIn();
        $quote = $this->quote([]);

        $this->postJson(route('api.finance.quotes.decide', $quote), ['decision' => 'accepted'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'quote_not_sent');

        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();
        $this->postJson(route('api.finance.quotes.decide', $quote), ['decision' => 'declined'])
            ->assertOk()
            ->assertJsonPath('quote.status', 'declined');
        $this->assertNotNull($quote->fresh()?->declined_at);
    }

    public function test_a_line_cannot_point_at_another_owners_article(): void
    {
        $this->signIn();
        $foreign = new FinanceProduct;
        $foreign->forceFill(['user_id' => User::factory()->create()->id, 'kind' => 'hardware', 'name' => 'Not mine', 'price_net' => 1])->save();

        $this->postJson(route('api.finance.quotes.store'), [
            'lines' => [['desc' => 'x', 'qty' => 1, 'unitPrice' => 1, 'vatRate' => 19, 'productId' => $foreign->id]],
        ])->assertStatus(422);
    }

    public function test_another_owner_cannot_reach_the_quote(): void
    {
        $this->signIn();
        $quote = $this->quote([]);

        app('auth')->forgetGuards();
        $this->signIn(User::factory()->create());

        $this->putJson(route('api.finance.quotes.update', $quote), ['title' => 'Stolen'])->assertNotFound();
        $this->postJson(route('api.finance.quotes.send', $quote))->assertNotFound();
        $this->postJson(route('api.finance.quotes.convert', $quote))->assertNotFound();
        $this->getJson(route('api.finance.data'))->assertOk()->assertJsonPath('quotes', []);
    }

    public function test_the_catalogue_turns_into_a_line_in_one_place(): void
    {
        $this->signIn();
        $product = $this->article(['name' => 'Switch', 'description' => '24 Ports', 'unit' => 'Stück', 'price_net' => 249.9, 'vat_rate' => 19]);

        $this->getJson(route('api.finance.products.line', $product))
            ->assertOk()
            ->assertJsonPath('line.desc', "Switch\n24 Ports")
            ->assertJsonPath('line.qty', 1)
            ->assertJsonPath('line.unit', 'Stück')
            ->assertJsonPath('line.unitPrice', 249.9)
            ->assertJsonPath('line.productId', $product->id);
    }

    public function test_expiry_is_derived_from_the_date_not_stored(): void
    {
        $this->signIn();
        $quote = $this->quote([]);
        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();

        $fresh = $quote->fresh();
        $fresh?->forceFill(['valid_until' => now()->subDay()])->save();
        $this->assertTrue($quote->fresh()?->isExpired());

        // The stored status still says what happened; only the reading changes.
        $this->assertSame('sent', (string) $quote->fresh()?->status);
    }

    public function test_a_rendered_pdf_is_stored_served_and_replaced(): void
    {
        Storage::fake((string) config('files.disk'));
        $this->signIn();
        $quote = $this->quote([]);
        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();

        // No PDF yet: there is nothing to preview.
        $this->get(route('api.finance.quotes.pdf', $quote))->assertNotFound();

        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
        ])->assertOk();

        $first = $quote->fresh()?->pdf_path;
        $this->assertIsString($first);
        // Server-generated path: a client never gets to choose where it lands.
        $this->assertStringStartsWith('invoices/', (string) $first);

        $res = $this->get(route('api.finance.quotes.pdf', $quote))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('inline', (string) $res->headers->get('Content-Disposition'));

        // ?download=1 is the same bytes as an attachment.
        $this->assertStringContainsString(
            'attachment',
            (string) $this->get(route('api.finance.quotes.pdf', [$quote, 'download' => 1]))->assertOk()->headers->get('Content-Disposition'),
        );

        // Re-rendering replaces the old document instead of leaving it behind.
        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
        ])->assertOk();
        $second = $quote->fresh()?->pdf_path;
        $this->assertNotSame($first, $second);
        Storage::disk((string) config('files.disk'))->assertMissing((string) $first);
        Storage::disk((string) config('files.disk'))->assertExists((string) $second);
    }

    public function test_only_a_pdf_is_accepted(): void
    {
        // Defence in depth on top of the serve-time sandbox: an svg or html
        // document served inline is the classic stored-XSS route.
        Storage::fake((string) config('files.disk'));
        $this->signIn();
        $quote = $this->quote([]);

        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('evil.svg', 4, 'image/svg+xml'),
        ])->assertStatus(422);
    }

    public function test_another_owner_cannot_read_or_replace_the_pdf(): void
    {
        Storage::fake((string) config('files.disk'));
        $this->signIn();
        $quote = $this->quote([]);
        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
        ])->assertOk();

        app('auth')->forgetGuards();
        $this->signIn(User::factory()->create());

        $this->get(route('api.finance.quotes.pdf', $quote))->assertNotFound();
        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('other.pdf', 12, 'application/pdf'),
        ])->assertNotFound();
    }

    public function test_deleting_a_quote_for_good_takes_its_pdf_with_it(): void
    {
        Storage::fake((string) config('files.disk'));
        $this->signIn();
        $quote = $this->quote([]);
        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
        ])->assertOk();
        $path = (string) $quote->fresh()?->pdf_path;

        $this->deleteJson(route('api.finance.quotes.destroy', $quote))->assertOk();
        // Still in the bin: the document is still reachable.
        Storage::disk((string) config('files.disk'))->assertExists($path);

        $this->deleteJson(route('api.finance.quotes.force', $quote->id))->assertOk();
        Storage::disk((string) config('files.disk'))->assertMissing($path);
    }

    public function test_mailing_needs_an_issued_quote_a_pdf_and_a_mailer(): void
    {
        Storage::fake((string) config('files.disk'));
        Mail::fake();
        $user = $this->signIn();
        $quote = $this->quote(['customer' => ['name' => 'Kunde', 'email' => 'kunde@example.com']]);

        // Nothing issued yet.
        $this->postJson(route('api.finance.quotes.email', $quote))
            ->assertStatus(422)
            ->assertJsonPath('error', 'quote_not_sent');

        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();

        // Issued but never rendered: a mail without its quote would be worse
        // than no mail.
        $this->postJson(route('api.finance.quotes.email', $quote))
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_pdf');

        $this->post(route('api.finance.quotes.pdf.store', $quote), [
            'file' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
        ])->assertOk();

        // No company mailer configured.
        $this->postJson(route('api.finance.quotes.email', $quote))
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_smtp');

        UserSetting::for($user->id)->forceFill([
            'company_smtp_enabled' => true,
            'company_smtp_host' => 'smtp.example.com',
            'company_smtp_port' => 587,
            'company_smtp_from_address' => 'buero@example.com',
        ])->save();

        $this->postJson(route('api.finance.quotes.email', $quote))->assertOk()->assertJsonPath('ok', true);
        Mail::assertSent(QuoteMail::class);
        $this->assertNotNull($quote->fresh()?->sent_at);
    }

    /** @param array<string, mixed> $attrs */
    private function quote(array $attrs): FinanceQuote
    {
        $quote = new FinanceQuote;
        $quote->fill(array_merge([
            'title' => 'Angebot',
            'issue_date' => '2026-05-04',
            'customer' => ['name' => 'Kunde'],
            'lines' => [['desc' => 'Arbeit', 'qty' => 1, 'unit' => 'Stunde', 'unitPrice' => 100, 'vatRate' => 19, 'kind' => 'service', 'productId' => null]],
            'net' => 100, 'vat' => 19, 'gross' => 119,
        ], $attrs));
        $quote->save();

        return $quote;
    }

    /** @param array<string, mixed> $attrs */
    private function article(array $attrs): FinanceProduct
    {
        $product = new FinanceProduct;
        $product->fill(array_merge(['kind' => 'hardware', 'name' => 'Switch', 'price_net' => 100], $attrs));
        $product->save();

        return $product;
    }
}
