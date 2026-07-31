<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
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
 * conflicts, encryption-at-rest of the sensitive columns, GoBD gapless per-year
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
        $this->postJson(route('finance.categories.store'), ['name' => 'Travel'])->assertCreated();
        $this->postJson(route('finance.categories.store'), ['name' => 'Travel'])->assertInvalid(['name']);
        $this->assertSame(1, FinanceCategory::query()->count());
    }

    public function test_sensitive_columns_encrypted_at_rest(): void
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

        // Encrypted columns never store the plaintext.
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

        // Round-trips through the encrypted casts.
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
}
