<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlobAuditLog;
use App\Models\GalleryBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The blob/shard forensic trail: every mutation of a content blob and every sealed
 * sharded-store root write is recorded so a data-loss event is fully traceable.
 */
class BlobAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_upload_records_a_create_event_with_a_ciphertext_hash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('enc.bin', 8)])
            ->assertCreated();

        $row = BlobAuditLog::where('action', 'create')->where('module', 'gallery')->first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('upload', $row->reason);
        $this->assertNotNull($row->sha256);
        $this->assertSame(64, strlen((string) $row->sha256));
        $this->assertNotNull($row->blob);
    }

    public function test_delete_records_a_delete_event(): void
    {
        $user = User::factory()->create();
        $disk = Storage::disk(config('files.disk'));
        $blob = (string) Str::uuid();
        $disk->put('gallery/'.$blob, 'x');
        GalleryBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->actingAs($user)->deleteJson(route('gallery.blob.destroy', ['blob' => $blob]))->assertOk();

        $row = BlobAuditLog::where('action', 'delete')->where('blob', $blob)->first();
        $this->assertNotNull($row);
        $this->assertSame('client_delete', $row->reason);
        $this->assertSame(10, $row->size);
    }

    public function test_reconcile_records_a_reconcile_delete_per_freed_blob(): void
    {
        $user = User::factory()->create();
        $disk = Storage::disk(config('files.disk'));
        $live = (string) Str::uuid();
        $orphan = (string) Str::uuid();
        foreach ([$live, $orphan] as $b) {
            $disk->put('gallery/'.$b, 'x');
        }
        GalleryBlob::create(['blob' => $live, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        GalleryBlob::create(['blob' => $orphan, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);

        $this->actingAs($user)->postJson(route('gallery.blobs.reconcile'), ['blobs' => [$live]])->assertOk();

        $this->assertDatabaseHas('blob_audit_log', [
            'action' => 'reconcile_delete',
            'blob' => $orphan,
            'reason' => 'reconcile',
        ]);
        // The still-referenced blob is never touched, so it is never logged as freed.
        $this->assertDatabaseMissing('blob_audit_log', ['action' => 'reconcile_delete', 'blob' => $live]);
    }

    public function test_root_write_records_the_shard_set_fingerprint(): void
    {
        $user = User::factory()->create();
        $blob = (string) Str::uuid();
        GalleryBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->actingAs($user)
            ->putJson(route('gallery.store.save'), ['ciphertext' => 'sealed-root', 'version' => 0, 'shards' => [$blob]])
            ->assertOk();

        $row = BlobAuditLog::where('action', 'root_write')->where('module', 'gallery')->first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNotNull($row->sha256);                    // hash of the sealed root
        $this->assertSame(1, $row->meta['shard_count'] ?? null);
        $this->assertNotNull($row->meta['shard_set_sha256'] ?? null);
        $this->assertSame(1, $row->meta['version'] ?? null);
    }

    public function test_root_reject_records_the_missing_shard(): void
    {
        $user = User::factory()->create();
        $ghost = (string) Str::uuid();

        $this->actingAs($user)
            ->putJson(route('gallery.store.save'), ['ciphertext' => 'x', 'version' => 0, 'shards' => [$ghost]])
            ->assertStatus(422);

        $row = BlobAuditLog::where('action', 'root_reject')->where('module', 'gallery')->first();
        $this->assertNotNull($row);
        $this->assertSame('missing_shard', $row->reason);
        $this->assertSame('rejected', $row->result);
        $this->assertContains($ghost, $row->meta['missing'] ?? []);
    }

    public function test_audit_rows_carry_no_ciphertext_or_keys(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('enc.bin', 8)])
            ->assertCreated();

        // The only content-derived field is a hex sha256 — never the bytes themselves.
        foreach (BlobAuditLog::all() as $row) {
            $this->assertTrue($row->sha256 === null || preg_match('/^[0-9a-f]{64}$/', (string) $row->sha256) === 1);
        }
    }

    public function test_entries_are_append_only(): void
    {
        $row = BlobAuditLog::create(['module' => 'gallery', 'action' => 'create', 'result' => 'ok', 'created_at' => now()]);
        $this->expectExceptionMessage('append-only');
        $row->update(['action' => 'delete']);
    }
}
