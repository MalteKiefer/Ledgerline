<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\FetchExchangeRates;
use App\Models\BankTransaction;
use App\Models\FileEntry;
use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FinanceReceipt;
use App\Models\GalleryPhoto;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /**
     * A project also collects evidence that is not money: documents from Files
     * and photos from the Gallery. The pointer is set through the owning
     * module's update endpoint; this reads it back, owner-scoped.
     */
    public function test_project_collects_files_and_photos_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $project = FinanceProject::create(['name' => 'Cellar', 'kind' => 'private']);

        $file = FileEntry::forceCreate([
            'user_id' => $owner->id, 'name' => 'permit.pdf', 'storage_path' => 'files/'.Str::uuid(),
            'mime' => 'application/pdf', 'size' => 12, 'sha256' => str_repeat('a', 64), 'version' => 0,
        ]);
        $photo = GalleryPhoto::forceCreate([
            'user_id' => $owner->id, 'name' => 'site.jpg', 'storage_path' => 'gallery/'.Str::uuid(),
            'mime' => 'image/jpeg', 'size' => 34, 'sha256' => str_repeat('b', 64), 'version' => 0,
        ]);

        // Empty until something is filed against it.
        $this->getJson(route('finance.projects.attachments', $project->id))
            ->assertOk()->assertJsonCount(0, 'files')->assertJsonCount(0, 'photos');

        $this->putJson(route('files.rel.update', $file->id), ['finance_project_id' => $project->id, 'version' => 0])->assertOk();
        $this->putJson(route('gallery.update', $photo->id), ['finance_project_id' => $project->id, 'version' => 0])->assertOk();

        $this->getJson(route('finance.projects.attachments', $project->id))
            ->assertOk()
            ->assertJsonCount(1, 'files')->assertJsonPath('files.0.name', 'permit.pdf')
            ->assertJsonCount(1, 'photos')->assertJsonPath('photos.0.name', 'site.jpg');

        // Unlinking clears the pointer without touching the file or photo itself.
        $this->putJson(route('files.rel.update', $file->id), ['finance_project_id' => null])->assertOk();
        $this->assertNull(FileEntry::findOrFail($file->id)->finance_project_id);
        $this->assertNotNull(FileEntry::findOrFail($file->id)->storage_path);

        // A stranger cannot see the project, let alone what hangs off it.
        $this->actingAs(User::factory()->create());
        $this->getJson(route('finance.projects.attachments', $project->id))->assertNotFound();
    }

    /**
     * A document attached at a booking keeps the content signature the receipt
     * inbox dedups on, so uploading the same file again is recognised instead of
     * silently becoming a second copy. (The entry lives inside
     * bank_transactions.receipts[], not as a finance_receipts row — which is
     * also why the documents queue has to read it from there.)
     */
    public function test_transaction_receipt_keeps_its_content_signature(): void
    {
        $this->actingAs(User::factory()->create());
        $pm = $this->postJson(route('finance.payment-methods.store'), ['name' => 'Bank', 'type' => 'bank'])
            ->assertCreated()->json('payment_method.id');
        $tx = $this->postJson(route('finance.transactions.store'), [
            'payment_method_id' => $pm, 'date' => '2026-03-01', 'amount' => -19.99,
        ])->assertCreated()->json('transaction.id');

        $res = $this->post(route('finance.transactions.receipts.store', $tx), [
            'file' => UploadedFile::fake()->create('invoice.pdf', 4, 'application/pdf'),
            'sig' => '1234:abc',
        ])->assertCreated();

        $this->assertSame('1234:abc', $res->json('transaction.receipts.0.sig'));

        // A metadata edit through the transaction PUT must not drop it (the
        // sanitiser rebuilds the array from client input).
        $entry = $res->json('transaction.receipts.0');
        $entry['category'] = 'Material';
        $this->putJson(route('finance.transactions.update', $tx), [
            'receipts' => [$entry], 'version' => $res->json('transaction.version'),
        ])->assertOk()->assertJsonPath('transaction.receipts.0.sig', '1234:abc');
    }

    public function test_sensitive_columns_stored_plaintext_at_rest(): void
    {
        $this->actingAs(User::factory()->create());

        $pm = $this->postJson(route('finance.payment-methods.store'), [
            'type' => 'bank', 'name' => 'Main', 'iban' => 'DE00SECRETIBAN0001',
        ])->json('payment_method.id');

        // Invoice creation itself now happens exclusively through the finance-v2
        // module (Task 17 cutover); this test's concern -- that the legacy
        // `invoices` table stores its columns plaintext, not encrypted -- is
        // about the historical table itself, independent of which writer
        // populated a row, so it stays provable with a direct row insert.
        $inv = Invoice::create([
            'issue_date' => '2026-03-01', 'status' => 'draft', 'currency' => 'EUR',
            'customer' => ['name' => 'Secret Customer AG', 'email' => 'c@example.com'],
            'lines' => [['desc' => 'Consulting', 'amount' => 100]],
            'gross' => 119, 'net' => 100, 'vat' => 19, 'vat_rate' => 19,
        ])->id;

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

    // GoBD gapless per-year numbering used to be tested here against the legacy
    // FinanceController::storeInvoice/finalizeInvoice routes, now deleted (Task
    // 17 cutover). It is covered equivalently for finance-v2's own allocator by
    // tests/Feature/FinanceModule/InvoiceFinalizationTest.php::
    // test_committed_number_allocations_are_never_reused_and_are_owner_year_scoped().

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

    // Client-uploaded, per-invoice PDF storage (with its owner-scoped streaming)
    // used to be tested here against the legacy FinanceController::
    // uploadInvoicePdf/invoicePdf routes, now deleted (Task 17 cutover).
    // finance-v2 has no client-writable PDF path at all -- every invoice PDF is
    // server-rendered and stored by CreateInvoiceDraftFromSource's finalize
    // pipeline, eliminating this entire mechanism (and the IDOR surface it
    // carried) by design; its owner-scoped streaming is covered by
    // tests/Feature/FinanceModule/InvoicePdfTest.php.

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

    // The legacy status vocabulary (final/sent/paid; a numbered invoice can never
    // revert to draft; once paid, status is terminal except via Storno) used to
    // be tested here against the now-deleted legacy FinanceController::
    // storeInvoice/finalizeInvoice/updateInvoice routes.
    //
    // finance-v2 does not carry the same risk forward, and not because an
    // equivalent guard was added: it never exposes a route that lets a client
    // set an invoice's status directly at all (Task 14 deliberately did not add
    // one -- see .superpowers/sdd/invoice-task-14-report.md). Status only ever
    // moves through specific, narrow actions (finalize/deliver/cancel/allocate
    // a payment), each already tested where it lives:
    // tests/Feature/FinanceModule/InvoiceApiTest.php::
    // test_finalize_deliver_and_cancel_use_idempotency_and_expose_no_storage_internals()
    // and every test in InvoiceCancellationTest.php. A client-supplied "set
    // status to draft/final/sent/paid" is not blocked by a check; it is simply
    // not a request finance-v2 understands.
    //
    // The same three tests also covered partner_id, discount_type, and
    // customer.attn/vatId on invoice creation -- covered for finance-v2 by
    // InvoiceApiTest's draft-creation assertions and this file's remaining
    // test_partner_stores_hourly_rate_and_currency() for the partner side. One
    // field genuinely has no finance-v2 equivalent: Skonto (an early-payment
    // cash discount, skonto_percent/skonto_days). That is a pre-existing gap in
    // the finance-v2 invoice module predating this cutover (it was never added
    // in the module's original build, Tasks 1-11) -- not something this task
    // introduces or is positioned to add; LegacyInvoiceReadProjection reports it
    // as always null for a finance-v2-sourced row, honestly rather than
    // fabricating a value.

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

    public function test_standalone_receipt_stores_currency_and_order_ref_and_uppercases_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('beleg.pdf', 20, 'application/pdf'),
            'currency' => 'usd', 'order_ref' => 'B2-20260205113013',
        ])->assertCreated()->json('receipt.id');

        $r = FinanceReceipt::find($id);
        $this->assertSame('USD', $r->currency);
        $this->assertSame('B2-20260205113013', $r->order_ref);

        $this->putJson(route('finance.receipts.update', $id), ['currency' => 'gbp', 'version' => 0])
            ->assertOk()->assertJsonPath('receipt.currency', 'GBP');
    }

    public function test_uploading_a_receipt_with_a_sig_already_on_file_returns_the_existing_row_instead_of_a_duplicate(): void
    {
        // Real case: a user re-dropped a whole batch to retry files a rate limit
        // failed, silently re-uploading files that had already succeeded.
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('netcup.pdf', 20, 'application/pdf'),
            'name' => '20260722; netcup GmbH; nc-5384423',
            'sig' => 'same-bytes-sig',
        ])->assertCreated();
        $id = $first->json('receipt.id');
        $this->assertNull($first->json('duplicate'));

        // Same sig again (even with different metadata attached this time) —
        // no second row, no second blob; the ORIGINAL receipt comes back.
        $again = $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('netcup-2.pdf', 20, 'application/pdf'),
            'name' => 'a different name this time',
            'sig' => 'same-bytes-sig',
        ])->assertOk();
        $this->assertTrue($again->json('duplicate'));
        $this->assertSame($id, $again->json('receipt.id'));
        $this->assertSame('20260722; netcup GmbH; nc-5384423', $again->json('receipt.name'));

        $this->assertSame(1, FinanceReceipt::count());

        // No sig at all — dedup never engages, a normal second row is created.
        $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('unrelated.pdf', 20, 'application/pdf'),
        ])->assertCreated();
        $this->assertSame(2, FinanceReceipt::count());

        // A different owner's identical bytes/sig is NOT deduped against — sig
        // lookup is scoped to the caller via the OwnsUserData global scope.
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('same-bytes-other-user.pdf', 20, 'application/pdf'),
            'sig' => 'same-bytes-sig',
        ])->assertCreated();
        // count() runs under the owner scope, so from here it reports the new
        // owner's single receipt. The global count proves nothing was reused:
        // three rows exist, one of them theirs.
        $this->assertSame(1, FinanceReceipt::count());
        $this->assertSame(3, FinanceReceipt::withoutGlobalScopes()->count());
    }

    public function test_standalone_receipt_split_link_is_mutually_exclusive_with_the_single_link(): void
    {
        // The real INWX case: one receipt, settled by two separate bank charges.
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = PaymentMethod::create(['type' => 'bank', 'name' => 'Business', 'business' => true]);
        $txA = BankTransaction::create(['payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -32.55, 'sig' => 'sig-a']);
        $txB = BankTransaction::create(['payment_method_id' => $pm->id, 'date' => '2026-07-25', 'amount' => -9.52, 'sig' => 'sig-b']);

        $id = $this->post(route('finance.receipts.store'), [
            'file' => UploadedFile::fake()->create('inwx.pdf', 20, 'application/pdf'),
            'amount' => 42.07,
        ])->assertCreated()->json('receipt.id');

        // Linking to several transactions clears any single link, and vice versa —
        // never both set at once, regardless of what the client sends together.
        $this->putJson(route('finance.receipts.update', $id), [
            'linked_transaction_ids' => [$txA->id, $txB->id], 'version' => 0,
        ])->assertOk()
            ->assertJsonPath('receipt.bank_transaction_id', null)
            ->assertJsonPath('receipt.linked_transaction_ids', [$txA->id, $txB->id]);

        $this->putJson(route('finance.receipts.update', $id), [
            'bank_transaction_id' => $txA->id, 'version' => 1,
        ])->assertOk()
            ->assertJsonPath('receipt.bank_transaction_id', $txA->id)
            ->assertJsonPath('receipt.linked_transaction_ids', null);

        // A transaction id belonging to someone else is rejected (owner-scoped
        // Rule::exists on linked_transaction_ids.*, not just the receipt binding).
        $other = User::factory()->create();
        $this->actingAs($other);
        $otherPm = PaymentMethod::create(['type' => 'bank', 'name' => 'Other', 'business' => true]);
        $otherTx = BankTransaction::create(['payment_method_id' => $otherPm->id, 'date' => '2026-07-18', 'amount' => -1.00, 'sig' => 'sig-other']);

        // The JSON contract lives on the API twin: the web route answers a
        // validation failure with the usual redirect. Assert the rejection where
        // it is specified, and assert the outcome that actually matters — the
        // foreign id never reaches the row.
        $this->actingAs($user);
        $this->putJson(route('api.finance.receipts.update', $id), [
            'linked_transaction_ids' => [$otherTx->id], 'version' => 2,
        ])->assertStatus(422);
        $this->assertNull(FinanceReceipt::query()->findOrFail($id)->linked_transaction_ids);
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

    public function test_finance_data_carries_exchange_rates_for_receipt_matching(): void
    {
        $user = User::factory()->create();

        // Without a fetched rate the configured fallback is served, so a
        // foreign-currency receipt can still be matched on a fresh install.
        Cache::forget(FetchExchangeRates::CACHE_KEY);
        $this->actingAs($user)->getJson(route('api.finance.data'))
            ->assertOk()
            ->assertJsonPath('fxRates.EUR', 1)
            ->assertJsonPath('fxRates.USD', 0.92);
    }

    public function test_finance_data_prefers_the_fetched_rates_over_the_fallback(): void
    {
        Cache::put(FetchExchangeRates::CACHE_KEY, ['usd' => 0.5]);

        $this->actingAs(User::factory()->create())->getJson(route('api.finance.data'))
            ->assertOk()
            ->assertJsonPath('fxRates.USD', 0.5);
    }
}
