<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FinanceReceipt;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Plaintext-relational Finance (pivot): CRUD for partners / payment methods /
 * projects / categories / invoices / bank transactions, optimistic version
 * conflicts, plaintext-at-rest of the sensitive columns, GoBD gapless per-year
 * numbering, bulk-import dedup by signature, invoice PDF + receipt file storage
 * with owner-scope, and per-user isolation.
 */
class FinanceRelationalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_partner_crud_and_optimistic_conflict(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('finance.partners.store'), ['name' => 'ACME GmbH', 'category' => 'Hosting'])
            ->assertCreated()->assertJsonPath('partner.name', 'ACME GmbH')->json('partner.id');
        $this->assertIsInt($id);

        $this->getJson(route('finance.data'))->assertOk()->assertJsonCount(1, 'partners');

        // Optimistic version: matching bumps, stale → 409.
        $this->putJson(route('finance.partners.update', $id), ['name' => 'ACME AG', 'version' => 0])
            ->assertOk()->assertJsonPath('partner.name', 'ACME AG')->assertJsonPath('partner.version', 1);
        $this->putJson(route('finance.partners.update', $id), ['name' => 'Nope', 'version' => 0])->assertStatus(409);
        $this->assertSame('ACME AG', FinancePartner::findOrFail($id)->name);

        // Trash → restore → force.
        $this->deleteJson(route('finance.partners.destroy', $id))->assertOk();
        $this->assertSame(1, FinancePartner::onlyTrashed()->count());
        $this->getJson(route('finance.trash'))->assertOk()->assertJsonCount(1, 'partners');
        $this->postJson(route('finance.partners.restore', $id))->assertOk();
        $this->assertSame(0, FinancePartner::onlyTrashed()->count());
        $this->deleteJson(route('finance.partners.destroy', $id))->assertOk();
        $this->deleteJson(route('finance.partners.force', $id))->assertOk();
        $this->assertDatabaseMissing('finance_partners', ['id' => $id]);
    }

    public function test_payment_method_crud(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('finance.payment-methods.store'), [
            'type' => 'bank', 'name' => 'Main', 'business' => true, 'iban' => 'DE89370400440532013000',
        ])->assertCreated()->assertJsonPath('payment_method.type', 'bank')->json('payment_method.id');

        $this->putJson(route('finance.payment-methods.update', $id), ['name' => 'Business', 'version' => 0])
            ->assertOk()->assertJsonPath('payment_method.name', 'Business');

        $this->getJson(route('finance.data'))->assertOk()->assertJsonCount(1, 'paymentMethods');
        $this->deleteJson(route('finance.payment-methods.destroy', $id))->assertOk();
        $this->assertSame(1, PaymentMethod::onlyTrashed()->count());
    }

    public function test_project_crud_and_category_uniqueness(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = $this->postJson(route('finance.projects.store'), ['name' => 'House', 'kind' => 'private'])
            ->assertCreated()->json('project.id');
        $this->postJson(route('finance.projects.store'), ['name' => 'Basement', 'parent_id' => $parent])->assertCreated();
        $this->assertSame(2, FinanceProject::query()->count());

        // Move with cycle guard: making the parent a child of its own child is refused.
        $child = FinanceProject::query()->where('name', 'Basement')->firstOrFail()->id;
        $this->postJson(route('finance.projects.move', $parent), ['parent_id' => $child])->assertStatus(422);

        // Categories: create + duplicate name for the same user is rejected.
        // (Web routes redirect on framework validation failures — the app only
        // JSON-renders exceptions for api/* — so assert the validation error, not
        // a status code.)
        $catId = $this->postJson(route('finance.categories.store'), ['name' => 'Travel', 'color' => '#6750a4'])
            ->assertCreated()->assertJsonPath('category.color', '#6750a4')->json('category.id');
        $this->postJson(route('finance.categories.store'), ['name' => 'Travel'])->assertInvalid(['name']);
        $this->assertSame(1, FinanceCategory::query()->count());

        // Recolor + delete (the category manager UI relies on these).
        $this->putJson(route('finance.categories.update', $catId), ['name' => 'Travel', 'color' => '#123456'])
            ->assertOk()->assertJsonPath('category.color', '#123456');
        $this->deleteJson(route('finance.categories.destroy', $catId))->assertOk();
        $this->assertSame(0, FinanceCategory::query()->count());
    }

    public function test_sensitive_columns_stored_plaintext_at_rest(): void
    {
        $this->actingAs(User::factory()->create());

        $pm = $this->postJson(route('finance.payment-methods.store'), [
            'type' => 'bank', 'name' => 'Main', 'iban' => 'DE00SECRETIBAN0001',
        ])->json('payment_method.id');

        $inv = $this->postJson(route('finance.invoices.store'), [
            'issue_date' => '2026-03-01',
            'customer' => ['name' => 'Secret Customer AG', 'email' => 'c@example.com'],
            'lines' => [['desc' => 'Consulting', 'amount' => 100]],
            'gross' => 119, 'net' => 100, 'vat' => 19, 'vat_rate' => 19,
        ])->assertCreated()->json('invoice.id');

        $tx = $this->postJson(route('finance.transactions.store'), [
            'payment_method_id' => $pm, 'date' => '2026-03-02', 'amount' => -50,
            'counterparty' => 'Secret Vendor Ltd', 'purpose' => 'Office supplies',
        ])->assertCreated()->json('transaction.id');

        // Columns are plaintext at rest (encryption removed in v1.516.0).
        $rawPm = DB::table('payment_methods')->where('id', $pm)->first();
        $this->assertNotNull($rawPm);
        $this->assertStringContainsString('DE00SECRETIBAN0001', (string) $rawPm->iban);

        $rawInv = DB::table('invoices')->where('id', $inv)->first();
        $this->assertNotNull($rawInv);
        $this->assertStringContainsString('Secret Customer AG', (string) $rawInv->customer);
        // Money columns stay plaintext for stats.
        $this->assertStringContainsString('119', (string) $rawInv->gross);

        $rawTx = DB::table('bank_transactions')->where('id', $tx)->first();
        $this->assertNotNull($rawTx);
        $this->assertStringContainsString('Secret Vendor Ltd', (string) $rawTx->counterparty);
        $this->assertStringContainsString('-50', (string) $rawTx->amount);

        // Round-trips through the array casts (plaintext).
        $this->assertSame('DE00SECRETIBAN0001', PaymentMethod::findOrFail($pm)->iban);
        $this->assertSame('Secret Customer AG', Invoice::findOrFail($inv)->customer['name'] ?? null);
    }

    public function test_gobd_gapless_per_year_numbering(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        UserSetting::for((int) $user->id)->update(['invoice_number_format' => 'YYYY-NNNN', 'invoice_next_number' => 1]);

        $a = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-05-01'])->json('invoice.id');
        $b = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-05-02'])->json('invoice.id');

        $na = $this->postJson(route('finance.invoices.finalize', $a))->assertOk()->json('invoice.number');
        $nb = $this->postJson(route('finance.invoices.finalize', $b))->assertOk()->json('invoice.number');

        $this->assertSame('2026-0001', $na);
        $this->assertSame('2026-0002', $nb);

        // Re-finalising an already-numbered invoice is idempotent (no new number).
        $this->postJson(route('finance.invoices.finalize', $a))->assertOk()->assertJsonPath('invoice.number', '2026-0001');

        // Numbers are unique and gapless.
        $numbers = Invoice::query()->whereNotNull('number')->pluck('number')->all();
        $this->assertSame($numbers, array_unique($numbers));
        $this->assertEqualsCanonicalizing(['2026-0001', '2026-0002'], $numbers);

        // A new year restarts the sequence.
        $c = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2027-01-01'])->json('invoice.id');
        $this->postJson(route('finance.invoices.finalize', $c))->assertOk()->assertJsonPath('invoice.number', '2027-0001');
    }

    public function test_bulk_transaction_dedup_by_signature(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->postJson(route('finance.payment-methods.store'), ['type' => 'bank', 'name' => 'Main'])->json('payment_method.id');

        $rows = [
            ['date' => '2026-06-01', 'amount' => -10, 'sig' => 'aaa', 'purpose' => 'A'],
            ['date' => '2026-06-02', 'amount' => -20, 'sig' => 'bbb', 'purpose' => 'B'],
            ['date' => '2026-06-01', 'amount' => -10, 'sig' => 'aaa', 'purpose' => 'A'], // dup within batch
        ];
        $this->postJson(route('finance.transactions.bulk'), ['payment_method_id' => $pm, 'transactions' => $rows])
            ->assertCreated()->assertJsonPath('created', 2)->assertJsonPath('skipped', 1);

        // Re-importing the same signatures skips them all.
        $this->postJson(route('finance.transactions.bulk'), ['payment_method_id' => $pm, 'transactions' => $rows])
            ->assertCreated()->assertJsonPath('created', 0)->assertJsonPath('skipped', 3);

        $this->assertSame(2, BankTransaction::query()->count());
    }

    public function test_invoice_pdf_upload_download_and_owner_scope(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $inv = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-07-01'])->json('invoice.id');

        $path = $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'file' => UploadedFile::fake()->createWithContent('invoice.pdf', '%PDF-1.4 bytes'),
        ])->assertOk()->json('invoice.pdf_path');

        $this->assertIsString($path);
        $this->assertStringStartsWith('invoices/', $path);
        Storage::disk(config('files.disk'))->assertExists($path);
        $this->get(route('finance.invoices.pdf', $inv))->assertOk();

        // Another user cannot download this invoice's PDF (owner-scoped binding → 404).
        $this->actingAs(User::factory()->create());
        $this->get(route('finance.invoices.pdf', $inv))->assertNotFound();
    }

    public function test_receipt_attach_stream_and_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->postJson(route('finance.payment-methods.store'), ['type' => 'bank', 'name' => 'Main'])->json('payment_method.id');
        $tx = $this->postJson(route('finance.transactions.store'), ['payment_method_id' => $pm, 'date' => '2026-08-01', 'amount' => -30])->json('transaction.id');

        $this->postJson(route('finance.transactions.receipts.store', $tx), [
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', '%PDF bytes'),
            'category' => 'Office',
        ])->assertCreated();

        $fresh = BankTransaction::findOrFail($tx);
        $receipts = $fresh->receipts ?? [];
        $this->assertCount(1, $receipts);
        $rid = $receipts[0]['id'];
        $blobPath = $receipts[0]['blob_path'];
        $this->assertIsString($blobPath);
        Storage::disk(config('files.disk'))->assertExists($blobPath);

        $this->get(route('finance.transactions.receipts.raw', ['transaction' => $tx, 'receipt' => $rid]))->assertOk();

        $this->deleteJson(route('finance.transactions.receipts.destroy', ['transaction' => $tx, 'receipt' => $rid]))->assertOk();
        Storage::disk(config('files.disk'))->assertMissing($blobPath);
        $this->assertCount(0, BankTransaction::findOrFail($tx)->receipts ?? []);
    }

    public function test_transaction_update_rejects_client_supplied_receipt_blob_path(): void
    {
        Storage::fake(config('files.disk'));
        $user = User::factory()->create();
        $pm = $this->actingAs($user)->postJson(route('finance.payment-methods.store'), ['type' => 'bank', 'name' => 'Giro'])->json('payment_method.id');
        $tx = $this->actingAs($user)->postJson(route('finance.transactions.store'), ['payment_method_id' => $pm, 'date' => '2026-01-02', 'amount' => -9.99])->json('transaction.id');

        // Plant a secret the attacker must not be able to read via a poisoned path.
        Storage::disk(config('files.disk'))->put('secret/appkey.txt', 'TOP-SECRET');

        // A PUT that tries to inject an arbitrary blob_path (path traversal / absolute).
        $this->actingAs($user)->putJson(route('finance.transactions.update', $tx), [
            'payment_method_id' => $pm,
            'date' => '2026-01-02',
            'amount' => -9.99,
            'receipts' => [
                ['id' => 'evil', 'name' => 'x', 'blob_path' => '../secret/appkey.txt'],
                ['id' => 'evil2', 'name' => 'y', 'blob_path' => 'secret/appkey.txt'],
            ],
        ])->assertOk();

        $stored = BankTransaction::findOrFail($tx)->receipts ?? [];
        foreach ($stored as $r) {
            $this->assertArrayNotHasKey('blob_path', $r, 'a fileless PUT entry must never carry a client blob_path');
        }
        // The receipt-raw endpoint must not stream the injected path (404, not the secret).
        $this->actingAs($user)->get(route('finance.transactions.receipts.raw', ['transaction' => $tx, 'receipt' => 'evil']))->assertNotFound();
    }

    public function test_finance_data_is_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->actingAs($a)->postJson(route('finance.partners.store'), ['name' => 'Mine'])->json('partner.id');

        $this->actingAs($b)->getJson(route('finance.data'))->assertOk()->assertJsonCount(0, 'partners');
        // b's request is owner-scoped → the row is invisible → 404.
        $this->actingAs($b)->deleteJson(route('finance.partners.destroy', $id))->assertNotFound();
    }

    public function test_finalize_issues_to_open_not_sent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        UserSetting::for((int) $user->id)->update(['invoice_number_format' => 'YYYY-NNNN', 'invoice_next_number' => 1]);

        $id = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-05-01'])->json('invoice.id');
        // Finalising issues the invoice → status 'final' (Open), NOT 'sent'.
        $this->postJson(route('finance.invoices.finalize', $id))
            ->assertOk()
            ->assertJsonPath('invoice.status', 'final');
        $this->assertSame('final', Invoice::find($id)->status);
    }

    public function test_numbered_invoice_cannot_revert_to_draft(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        UserSetting::for((int) $user->id)->update(['invoice_number_format' => 'YYYY-NNNN', 'invoice_next_number' => 1]);

        $id = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-05-01'])->json('invoice.id');
        $this->postJson(route('finance.invoices.finalize', $id))->assertOk(); // now numbered + final

        // GoBD: a numbered (issued) invoice can never go back to draft.
        $this->putJson(route('finance.invoices.update', $id), ['status' => 'draft'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'status_draft_blocked');
        $this->assertSame('final', Invoice::find($id)->status);

        // A forward status change on a numbered invoice IS allowed.
        $this->putJson(route('finance.invoices.update', $id), ['status' => 'paid', 'paid_at' => '2026-05-10'])
            ->assertOk();
        $this->assertSame('paid', Invoice::find($id)->status);
    }

    public function test_invoice_stores_partner_discount_skonto_and_customer_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $partnerId = $this->postJson(route('finance.partners.store'), ['name' => 'Acme'])
            ->assertCreated()->json('partner.id');

        $id = $this->postJson(route('finance.invoices.store'), [
            'status' => 'draft',
            'currency' => 'EUR',
            'issue_date' => '2026-01-10',
            'partner_id' => $partnerId,
            'discount_type' => 'percent',
            'discount_value' => 5,
            'skonto_percent' => 2,
            'skonto_days' => 14,
            'customer' => ['name' => 'Acme', 'attn' => 'Jane', 'vatId' => 'DE123', 'address' => "Street 1\nCity"],
            'lines' => [['desc' => 'Work', 'qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
        ])->assertCreated()->json('invoice.id');

        $inv = Invoice::find($id);
        $this->assertSame($partnerId, $inv->partner_id);
        $this->assertSame('percent', $inv->discount_type);
        $this->assertEquals(2, (float) $inv->skonto_percent);
        $this->assertSame(14, $inv->skonto_days);
        $this->assertSame('Jane', $inv->customer['attn']);
        $this->assertSame('DE123', $inv->customer['vatId']);
    }

    public function test_partner_stores_hourly_rate_and_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson(route('finance.partners.store'), [
            'name' => 'Acme',
            'hourly_rate' => 95.5,
            'currency' => 'CHF',
        ])->assertCreated()->json('partner.id');

        $p = FinancePartner::find($id);
        $this->assertSame('95.50', $p->hourly_rate);
        $this->assertSame('CHF', $p->currency);
    }

    public function test_vat_advance_ist_counts_only_paid_by_payment_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $settings = UserSetting::for((int) $user->id);

        // One paid invoice (paid in Q2) + one still open (final, unpaid). imported=true so
        // totals derive from the gross/vat_rate columns (no line-item rows needed).
        Invoice::create(['status' => 'paid', 'imported' => true, 'issue_date' => '2026-01-10', 'paid_at' => '2026-05-10', 'year' => 2026,
            'currency' => 'EUR', 'vat_rate' => 19, 'net' => 100, 'vat' => 19, 'gross' => 119]);
        Invoice::create(['status' => 'final', 'imported' => true, 'issue_date' => '2026-01-15', 'year' => 2026,
            'currency' => 'EUR', 'vat_rate' => 19, 'net' => 200, 'vat' => 38, 'gross' => 238]);

        // Ist (default): only the paid invoice counts, booked to Q2.
        $settings->update(['invoice_vat_ist' => true]);
        $ist = $this->getJson(route('finance.reports.vat-advance', ['year' => 2026]))->assertOk()->json();
        $this->assertSame(19.0, (float) $ist['outputVat']);

        // Soll: both the paid and the open invoice count (issued).
        $settings->update(['invoice_vat_ist' => false]);
        $soll = $this->getJson(route('finance.reports.vat-advance', ['year' => 2026]))->assertOk()->json();
        $this->assertSame(57.0, (float) $soll['outputVat']);
    }

    public function test_standalone_receipt_upload_without_a_transaction(): void
    {
        // "Fremdbelege": a receipt document filed with no bank transaction.
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('beleg.pdf', 20, 'application/pdf'),
            'category' => 'Büro',
            'tags' => ['2026', 'quittung'],
        ])->assertCreated();
        $id = $res->json('receipt.id');
        $this->assertIsInt($id);
        $this->assertNull($res->json('receipt.bank_transaction_id'));

        $r = FinanceReceipt::find($id);
        $this->assertNotNull($r);
        $this->assertSame($user->id, $r->user_id);
        $this->assertStringStartsWith('invoices/', (string) $r->blob_path);
        $this->assertTrue(Storage::disk(config('files.disk'))->exists($r->blob_path));

        // Appears in the snapshot the page hydrates from.
        $this->getJson(route('finance.data'))->assertOk()->assertJsonPath('standaloneReceipts.0.id', $id);

        // Update (optimistic) + serve + soft-delete.
        $this->putJson(route('finance.receipts.update', $id), ['category' => 'Reise', 'version' => 0])
            ->assertOk()->assertJsonPath('receipt.category', 'Reise');
        $this->get(route('finance.receipts.raw', $id))->assertOk();
        $this->deleteJson(route('finance.receipts.destroy', $id))->assertOk();
        $this->assertSoftDeleted('finance_receipts', ['id' => $id]);
    }

    public function test_standalone_receipt_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $id = $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('receipt.id');

        $this->actingAs(User::factory()->create());
        $this->get(route('finance.receipts.raw', $id))->assertNotFound();
        $this->putJson(route('finance.receipts.update', $id), ['category' => 'x'])->assertNotFound();
    }
}
