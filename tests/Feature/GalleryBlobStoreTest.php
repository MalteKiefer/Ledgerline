<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryBlobStoreTest extends TestCase
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
        $blob = $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('enc.bin', 12)])
            ->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('gallery/'.$blob);
        $row = GalleryBlob::find($blob);
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame(12 * 1024, (int) $row->size);
    }

    public function test_upload_rejected_over_quota(): void
    {
        config(['gallery.quota_mb' => 1]);
        $user = $this->signIn();
        GalleryBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 1024 * 1024, 'created_at' => now()]);
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('m.bin', 4)])->assertStatus(413);
    }

    public function test_usage_reports_blob_bytes(): void
    {
        config(['gallery.quota_mb' => 5]);
        $user = $this->signIn();
        GalleryBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 2048, 'created_at' => now()]);
        $this->getJson(route('gallery.usage'))->assertOk()->assertJson(['used' => 2048, 'quota' => 5 * 1024 * 1024]);
    }

    public function test_raw_and_delete_are_owner_scoped(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('gallery/'.$blob, 'ciphertext');
        GalleryBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('gallery.raw', ['blob' => $blob]))
            ->assertOk()
            // Ciphertext is content-addressed and immutable, so it caches hard in
            // the browser — a second visit skips re-downloading every thumbnail.
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');
        $this->actingAs(User::factory()->create())->get(route('gallery.raw', ['blob' => $blob]))->assertNotFound();
        $this->actingAs(User::factory()->create())->deleteJson(route('gallery.blob.destroy', ['blob' => $blob]))->assertForbidden();

        $this->actingAs($user)->deleteJson(route('gallery.blob.destroy', ['blob' => $blob]))->assertOk();
        Storage::disk(config('files.disk'))->assertMissing('gallery/'.$blob);
        $this->assertNull(GalleryBlob::find($blob));
    }

    public function test_reconcile_reclaims_only_unreferenced_aged_blobs(): void
    {
        $user = $this->signIn();
        $disk = Storage::disk(config('files.disk'));
        $live = (string) Str::uuid();
        $orphanOld = (string) Str::uuid();
        $orphanNew = (string) Str::uuid();
        foreach ([$live, $orphanOld, $orphanNew] as $b) {
            $disk->put('gallery/'.$b, 'x');
        }
        GalleryBlob::create(['blob' => $live, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        GalleryBlob::create(['blob' => $orphanOld, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        GalleryBlob::create(['blob' => $orphanNew, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->postJson(route('gallery.blobs.reconcile'), ['blobs' => [$live]])->assertOk()->assertJson(['used' => 20]);

        $this->assertNotNull(GalleryBlob::find($live));
        $this->assertNotNull(GalleryBlob::find($orphanNew));
        $this->assertNull(GalleryBlob::find($orphanOld));
        $disk->assertMissing('gallery/'.$orphanOld);
    }
}
