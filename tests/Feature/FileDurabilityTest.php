<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function syncOneFile(User $user, string $id, string $blob, int $size = 10): void
    {
        // Sync only accepts blobs the user actually uploaded; register it and
        // put its bytes on disk so the server can read the authoritative size.
        FileBlob::firstOrCreate(['blob' => $blob], ['user_id' => $user->id, 'created_at' => now()]);
        Storage::disk(config('files.disk'))->put('files/'.$blob, str_repeat('x', $size));
        $this->actingAs($user)->putJson(route('files.sync'), [
            'folders' => [],
            'files' => [['id' => $id, 'blob' => $blob, 'enc_metadata' => 'sealed', 'enc_file_key' => 'wrapped', 'folder' => null, 'tags' => []]],
        ])->assertOk();
    }

    public function test_changing_a_files_blob_snapshots_the_old_blob_as_a_version(): void
    {
        Storage::fake(config('files.disk'));
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        $blobA = (string) Str::uuid();
        $blobB = (string) Str::uuid();

        $this->syncOneFile($user, $id, $blobA);
        $this->assertSame(0, FileVersion::where('file_id', $id)->count());

        // Re-sync the same file with a new blob (content changed).
        $this->syncOneFile($user, $id, $blobB);

        $versions = FileVersion::where('file_id', $id)->get();
        $this->assertCount(1, $versions);
        $this->assertSame($blobA, $versions->first()->blob);
        $this->assertSame($blobB, StoredFile::withoutGlobalScopes()->find($id)->blob);
    }

    public function test_upload_rejected_when_over_quota(): void
    {
        config()->set('files.quota_mb', 1); // 1 MiB
        Storage::fake(config('files.disk'));
        $user = User::factory()->create();

        // Occupy the quota with an existing file row owned by the user
        // (ownership is set from auth via AssignsOwner, so saveQuietly + explicit
        // user_id is the reliable way to seed it).
        $big = new StoredFile(['id' => (string) Str::uuid(), 'name' => 'big', 'mime' => 'application/octet-stream', 'size' => 1024 * 1024, 'blob' => (string) Str::uuid(), 'tags' => []]);
        $big->user_id = $user->id;
        $big->saveQuietly();

        $this->actingAs($user)->post(route('files.upload'), [
            'file' => UploadedFile::fake()->create('more.bin', 10),
        ])->assertStatus(413);
    }
}
