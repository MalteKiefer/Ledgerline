<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InvoiceBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Invoices shard blob endpoints (merge-safety spec §3b): upload records ownership,
 * raw is owner-scoped, and the client-driven reconcile reclaims orphaned shards.
 */
class InvoiceBlobStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_upload_stores_bytes_and_records_ownership(): void
    {
        $user = $this->signIn();

        $blob = $this->post(route('invoices.upload'), [
            'file' => UploadedFile::fake()->create('shard.enc', 4),
        ])->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('invoices/'.$blob);
        $row = InvoiceBlob::find($blob);
        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row->user_id);
    }

    public function test_raw_download_is_owner_scoped(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('invoices/'.$blob, 'ciphertext');
        InvoiceBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('invoices.raw', $blob))->assertOk();

        // Another user cannot read it.
        $this->signIn();
        $this->get(route('invoices.raw', $blob))->assertNotFound();
    }

    public function test_reconcile_reclaims_orphaned_shards_past_grace(): void
    {
        $user = $this->signIn();
        $disk = Storage::disk(config('files.disk'));
        $live = (string) Str::uuid();
        $orphan = (string) Str::uuid();
        foreach ([$live, $orphan] as $b) {
            $disk->put('invoices/'.$b, 'x');
            InvoiceBlob::create(['blob' => $b, 'user_id' => $user->id, 'size' => 5, 'created_at' => now()->subDays(3)]);
        }

        $this->postJson(route('invoices.blobs.reconcile'), ['blobs' => [$live]])->assertOk();

        $this->assertNotNull(InvoiceBlob::find($live));
        $this->assertNull(InvoiceBlob::find($orphan));
        $disk->assertMissing('invoices/'.$orphan);
    }
}
