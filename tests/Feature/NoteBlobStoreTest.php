<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NoteBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notes shard blob endpoints (merge-safety spec §3b): upload records ownership,
 * raw is owner-scoped, and the client-driven reconcile reclaims orphaned shards.
 */
class NoteBlobStoreTest extends TestCase
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

        $blob = $this->post(route('notes.upload'), [
            'file' => UploadedFile::fake()->create('shard.enc', 4),
        ])->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('notes/'.$blob);
        $row = NoteBlob::find($blob);
        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row->user_id);
    }

    public function test_raw_download_is_owner_scoped(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('notes/'.$blob, 'ciphertext');
        NoteBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('notes.raw', $blob))->assertOk();

        // Another user cannot read it.
        $this->signIn();
        $this->get(route('notes.raw', $blob))->assertNotFound();
    }

    public function test_reconcile_reclaims_orphaned_shards_past_grace(): void
    {
        $user = $this->signIn();
        $disk = Storage::disk(config('files.disk'));
        $live = (string) Str::uuid();
        $orphan = (string) Str::uuid();
        foreach ([$live, $orphan] as $b) {
            $disk->put('notes/'.$b, 'x');
            NoteBlob::create(['blob' => $b, 'user_id' => $user->id, 'size' => 5, 'created_at' => now()->subDays(3)]);
        }

        $this->postJson(route('notes.blobs.reconcile'), ['blobs' => [$live]])->assertOk();

        $this->assertNotNull(NoteBlob::find($live));
        $this->assertNull(NoteBlob::find($orphan));
        $disk->assertMissing('notes/'.$orphan);
    }
}
