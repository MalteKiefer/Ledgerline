<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Per-version invoice PDF storage (GoBD correction trail): each versions[] entry
 * keeps its OWN generated PDF blob so a historical version renders its own
 * document instead of the shared latest pdf_path. Covers server-side association
 * via version_seq, distinct blobs per version, owner-scoped + sandboxed
 * streaming, the client-path-injection guard, re-render replacement, and
 * force-delete cleanup.
 */
class InvoiceVersionPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /** @return array{0:User,1:int} owner + a persisted invoice carrying two version entries. */
    private function invoiceWithVersions(): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $inv = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-07-01'])->json('invoice.id');
        $this->assertIsInt($inv);

        // versions[] is fillable — seed two entries (as the client does on a versioned edit).
        $this->putJson(route('finance.invoices.update', $inv), [
            'version' => 0,
            'versions' => [
                ['seq' => 1, 'label' => 'R-1-001', 'reason' => 'first', 'at' => '2026-07-01T10:00:00Z'],
                ['seq' => 2, 'label' => 'R-1-002', 'reason' => 'second', 'at' => '2026-07-02T10:00:00Z'],
            ],
        ])->assertOk();

        return [$owner, $inv];
    }

    public function test_per_version_pdf_is_associated_with_its_entry_and_pdf_path_untouched(): void
    {
        [, $inv] = $this->invoiceWithVersions();

        $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 1,
            'file' => UploadedFile::fake()->createWithContent('v1.pdf', '%PDF v1 bytes'),
        ])->assertOk();

        $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 2,
            'file' => UploadedFile::fake()->createWithContent('v2.pdf', '%PDF v2 bytes'),
        ])->assertOk();

        $fresh = Invoice::findOrFail($inv);
        $versions = $fresh->versions ?? [];
        $this->assertCount(2, $versions);

        // Each version got its OWN distinct blob; the shared pdf_path stayed null.
        $p1 = $versions[0]['pdf'] ?? null;
        $p2 = $versions[1]['pdf'] ?? null;
        $this->assertIsString($p1);
        $this->assertIsString($p2);
        $this->assertNotSame($p1, $p2);
        $this->assertStringStartsWith('invoices/', $p1);
        $this->assertStringStartsWith('invoices/', $p2);
        $this->assertNull($fresh->pdf_path);
        Storage::disk(config('files.disk'))->assertExists($p1);
        Storage::disk(config('files.disk'))->assertExists($p2);

        // Streaming a version returns THAT version's document, sandboxed + nosniff.
        $r1 = $this->get(route('finance.invoices.pdf', $inv).'?version_seq=1')->assertOk();
        $this->assertSame("default-src 'none'; sandbox", $r1->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $r1->headers->get('X-Content-Type-Options'));
        $this->assertSame('%PDF v1 bytes', $r1->streamedContent());

        $this->assertSame(
            '%PDF v2 bytes',
            $this->get(route('finance.invoices.pdf', $inv).'?version_seq=2')->assertOk()->streamedContent(),
        );
    }

    public function test_re_render_replaces_the_versions_own_blob(): void
    {
        [, $inv] = $this->invoiceWithVersions();

        $first = $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 1,
            'file' => UploadedFile::fake()->createWithContent('v1.pdf', 'old'),
        ])->assertOk()->json('invoice.versions.0.pdf');

        $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 1,
            'file' => UploadedFile::fake()->createWithContent('v1b.pdf', 'new'),
        ])->assertOk();

        $second = Invoice::findOrFail($inv)->versions[0]['pdf'] ?? null;
        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
        Storage::disk(config('files.disk'))->assertMissing($first);
        Storage::disk(config('files.disk'))->assertExists($second);
        $this->assertSame('new', $this->get(route('finance.invoices.pdf', $inv).'?version_seq=1')->streamedContent());
    }

    public function test_upload_for_unknown_version_drops_the_orphan_blob(): void
    {
        [, $inv] = $this->invoiceWithVersions();

        $before = Storage::disk(config('files.disk'))->allFiles();
        $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 99,
            'file' => UploadedFile::fake()->createWithContent('nope.pdf', 'x'),
        ])->assertOk();

        // No version matched → nothing persisted, uploaded blob removed.
        $this->assertSame($before, Storage::disk(config('files.disk'))->allFiles());
        foreach (Invoice::findOrFail($inv)->versions ?? [] as $v) {
            $this->assertArrayNotHasKey('pdf', $v);
        }
    }

    public function test_version_pdf_streaming_is_owner_scoped(): void
    {
        [, $inv] = $this->invoiceWithVersions();
        $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 1,
            'file' => UploadedFile::fake()->createWithContent('v1.pdf', '%PDF v1'),
        ])->assertOk();

        // A different user cannot reach it (owner-scoped route binding → 404).
        $this->actingAs(User::factory()->create());
        $this->get(route('finance.invoices.pdf', $inv).'?version_seq=1')->assertNotFound();
    }

    public function test_streaming_rejects_client_poisoned_version_pdf_path(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $inv = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-07-01'])->json('invoice.id');

        Storage::disk(config('files.disk'))->put('secret/appkey.txt', 'TOP-SECRET');

        // versions[] is client-writable; a poisoned pdf path must not stream (safeBlobPath).
        $this->putJson(route('finance.invoices.update', $inv), [
            'version' => 0,
            'versions' => [
                ['seq' => 1, 'label' => 'evil', 'pdf' => '../secret/appkey.txt'],
                ['seq' => 2, 'label' => 'evil2', 'pdf' => 'secret/appkey.txt'],
            ],
        ])->assertOk();

        $this->get(route('finance.invoices.pdf', $inv).'?version_seq=1')->assertNotFound();
        $this->get(route('finance.invoices.pdf', $inv).'?version_seq=2')->assertNotFound();
    }

    public function test_force_delete_removes_all_version_pdf_blobs(): void
    {
        [, $inv] = $this->invoiceWithVersions();

        $p1 = $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 1, 'file' => UploadedFile::fake()->createWithContent('v1.pdf', 'a'),
        ])->assertOk()->json('invoice.versions.0.pdf');
        $p2 = $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'version_seq' => 2, 'file' => UploadedFile::fake()->createWithContent('v2.pdf', 'b'),
        ])->assertOk()->json('invoice.versions.1.pdf');
        // Also a shared/original pdf_path (no version_seq) for good measure.
        $main = $this->postJson(route('finance.invoices.pdf.upload', $inv), [
            'file' => UploadedFile::fake()->createWithContent('orig.pdf', 'm'),
        ])->assertOk()->json('invoice.pdf_path');

        $this->assertIsString($p1);
        $this->assertIsString($p2);
        $this->assertIsString($main);

        $this->deleteJson(route('finance.invoices.destroy', $inv))->assertOk();
        $this->deleteJson(route('finance.invoices.force', $inv))->assertOk();

        Storage::disk(config('files.disk'))->assertMissing($p1);
        Storage::disk(config('files.disk'))->assertMissing($p2);
        Storage::disk(config('files.disk'))->assertMissing($main);
        $this->assertDatabaseMissing('invoices', ['id' => $inv]);
    }
}
