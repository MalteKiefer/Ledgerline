<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The zero-knowledge file store keeps only opaque content blobs + a per-user
 * ownership ledger (file_blobs); the whole tree lives in the sealed manifest.
 * These cover the blob endpoints: upload/quota, owner-scoped raw + delete, the
 * usage report, and the client-driven reconcile that reclaims orphaned blobs.
 */
class FilesBlobStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
        $this->get(route('files.usage'))->assertRedirect(route('login'));
    }

    public function test_the_browser_page_loads(): void
    {
        $this->signIn();
        $this->get(route('files.index'))->assertOk();
    }

    public function test_upload_stores_bytes_and_records_ownership(): void
    {
        $user = $this->signIn();

        $blob = $this->post(route('files.upload'), [
            'file' => UploadedFile::fake()->create('blob.enc', 12),
        ])->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('files/'.$blob);
        $row = FileBlob::find($blob);
        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame(12 * 1024, (int) $row->size);
    }

    public function test_upload_is_rejected_over_quota(): void
    {
        config(['files.quota_mb' => 1]);
        $user = $this->signIn();
        // Already occupies the whole 1 MiB quota.
        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 1024 * 1024, 'created_at' => now()]);

        $this->post(route('files.upload'), ['file' => UploadedFile::fake()->create('more.enc', 4)])
            ->assertStatus(413);
    }

    public function test_usage_reports_the_users_blob_bytes(): void
    {
        config(['files.quota_mb' => 5]);
        $user = $this->signIn();
        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 2048, 'created_at' => now()]);
        // Another user's bytes never count toward this user's usage.
        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'size' => 9999, 'created_at' => now()]);

        $this->getJson(route('files.usage'))->assertOk()
            ->assertJson(['used' => 2048, 'quota' => 5 * 1024 * 1024]);
    }

    public function test_raw_download_is_owner_scoped(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$blob, 'ciphertext');
        FileBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('files.raw', ['blob' => $blob]))->assertOk();
        // Unknown blob = 404, never a directory-probe oracle.
        $this->get(route('files.raw', ['blob' => (string) Str::uuid()]))->assertNotFound();
    }

    public function test_delete_blob_is_owner_scoped_and_idempotent(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$blob, 'ciphertext');
        FileBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        // A stranger's delete is a uniform idempotent no-op (no ownership oracle);
        // the bytes survive.
        $this->actingAs(User::factory()->create())
            ->deleteJson(route('files.blob.destroy', ['blob' => $blob]))->assertOk();
        Storage::disk(config('files.disk'))->assertExists('files/'.$blob);

        // The owner deletes bytes + row; deleting again is a harmless no-op.
        $this->actingAs($user)->deleteJson(route('files.blob.destroy', ['blob' => $blob]))->assertOk();
        Storage::disk(config('files.disk'))->assertMissing('files/'.$blob);
        $this->assertNull(FileBlob::find($blob));
        $this->actingAs($user)->deleteJson(route('files.blob.destroy', ['blob' => $blob]))
            ->assertOk()->assertJson(['deleted' => true]);
    }

    public function test_reconcile_reclaims_only_unreferenced_aged_blobs(): void
    {
        $user = $this->signIn();
        $disk = Storage::disk(config('files.disk'));

        // Referenced (in the live set) — kept even though it is old.
        $live = (string) Str::uuid();
        // Orphaned + older than the grace window — reclaimed.
        $orphanOld = (string) Str::uuid();
        // Orphaned but fresh (in-flight upload not yet in a saved manifest) — kept.
        $orphanNew = (string) Str::uuid();
        foreach ([$live, $orphanOld, $orphanNew] as $b) {
            $disk->put('files/'.$b, 'ciphertext');
        }
        FileBlob::create(['blob' => $live, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        FileBlob::create(['blob' => $orphanOld, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        FileBlob::create(['blob' => $orphanNew, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->postJson(route('files.blobs.reconcile'), ['blobs' => [$live]])
            ->assertOk()->assertJson(['used' => 20]); // live + fresh orphan remain

        $this->assertNotNull(FileBlob::find($live));
        $this->assertNotNull(FileBlob::find($orphanNew));
        $this->assertNull(FileBlob::find($orphanOld));
        $disk->assertExists('files/'.$live);
        $disk->assertExists('files/'.$orphanNew);
        $disk->assertMissing('files/'.$orphanOld);
    }

    public function test_reconcile_never_touches_another_users_blobs(): void
    {
        $user = $this->signIn();
        $other = User::factory()->create();
        $theirs = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$theirs, 'ciphertext');
        FileBlob::create(['blob' => $theirs, 'user_id' => $other->id, 'size' => 10, 'created_at' => now()->subDays(3)]);

        // An empty live set from $user must not reap another user's aged blob.
        $this->postJson(route('files.blobs.reconcile'), ['blobs' => []])->assertOk();
        $this->assertNotNull(FileBlob::find($theirs));
        Storage::disk(config('files.disk'))->assertExists('files/'.$theirs);
    }
}
