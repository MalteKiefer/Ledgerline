<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\File;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(int $fileId, array $overrides = []): array
    {
        return array_merge([
            'file_id' => $fileId,
            'customer_mode' => 'new',
            'new_customer_name' => 'Acme GmbH',
            'new_customer_postal_code' => '53797',
            'new_customer_city' => 'Lohmar',
            'number' => '2026-004',
            'issue_date' => '2026-04-30',
            'status' => 'PAID',
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'lines' => [
                ['description' => 'Support', 'quantity' => 1, 'unit' => '', 'unit_price' => 149.85, 'tax_rate' => 19],
            ],
        ], $overrides);
    }

    public function test_upload_queues_files_and_the_review_slideshow_starts(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('finance.invoices.import.parse'), [
            'files' => [
                UploadedFile::fake()->create('one.pdf', 30, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 30, 'application/pdf'),
            ],
        ])->assertRedirect(route('finance.invoices.import.next'));

        $this->assertDatabaseHas('files', ['name' => 'one.pdf']);
        $this->assertDatabaseHas('files', ['name' => 'two.pdf']);
        $this->assertCount(2, session('import_queue'));

        $this->get(route('finance.invoices.import.next'))
            ->assertOk()
            ->assertViewIs('invoices.import.review')
            ->assertViewHas('total', 2);
    }

    public function test_skipping_discards_the_pending_file_and_advances(): void
    {
        Storage::fake('files');
        $this->signIn();
        $this->post(route('finance.invoices.import.parse'), [
            'files' => [UploadedFile::fake()->create('skip-me.pdf', 30, 'application/pdf')],
        ]);

        $this->post(route('finance.invoices.import.skip'))
            ->assertRedirect(route('finance.invoices.import.next'));

        $this->assertDatabaseMissing('files', ['name' => 'skip-me.pdf']);
        $this->assertEmpty(session('import_queue', []));
    }

    public function test_deleting_an_imported_invoices_file_deletes_the_invoice(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create();
        $this->post(route('finance.invoices.import.store'), $this->payload($file->id));
        $invoice = Invoice::firstWhere('number', '2026-004');

        $this->delete(route('files.destroy', $file->fresh()))
            ->assertRedirect(route('finance.invoices.index'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_store_creates_a_finalized_historical_invoice_and_attaches_the_pdf(): void
    {
        $this->signIn();
        $file = File::factory()->create(['name' => 'invoice.pdf']);

        $this->post(route('finance.invoices.import.store'), $this->payload($file->id))
            ->assertRedirect();

        $invoice = Invoice::firstWhere('number', '2026-004');
        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->finalized_at);
        $this->assertSame('PAID', $invoice->status->value);
        $this->assertSame(14985, $invoice->net_cents);
        $this->assertSame(17832, $invoice->gross_cents);
        $this->assertSame(17832, $invoice->paid_cents);
        $this->assertSame('Acme GmbH', $invoice->customer->name);
        $this->assertTrue($file->fresh()->attachable->is($invoice));
    }

    public function test_import_advances_the_internal_number_counter(): void
    {
        $this->signIn();
        $file = File::factory()->create();

        // Import a 2026 invoice numbered 2026-004 (no prefix, 3-digit pad).
        $this->post(route('finance.invoices.import.store'), $this->payload($file->id));

        // A newly created + finalised 2026 invoice must continue the series.
        $customer = Customer::factory()->create();
        $this->post(route('finance.invoices.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-05-01',
            'language' => 'de',
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 19]],
        ]);
        $draft = Invoice::where('number', null)->latest('id')->firstOrFail();
        $this->post(route('finance.invoices.finalize', $draft));

        $this->assertSame('2026-005', $draft->fresh()->number);
    }

    public function test_an_imported_invoice_can_be_deleted(): void
    {
        $this->signIn();
        $file = File::factory()->create();
        $this->post(route('finance.invoices.import.store'), $this->payload($file->id));
        $invoice = Invoice::firstWhere('number', '2026-004');

        $this->assertTrue($invoice->isImported());
        $this->delete(route('finance.invoices.destroy', $invoice))
            ->assertRedirect(route('finance.invoices.index'));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_import_number_must_be_unique(): void
    {
        $this->signIn();
        $file = File::factory()->create();
        $this->post(route('finance.invoices.import.store'), $this->payload($file->id));

        $file2 = File::factory()->create();
        $this->post(route('finance.invoices.import.store'), $this->payload($file2->id, ['number' => '2026-004']))
            ->assertSessionHasErrors('number');
    }
}
