<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesBlobSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u, string $blob): StoredFile
    {
        $f = new StoredFile;
        $f->forceFill([
            'id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => 'f-'.Str::random(4),
            'blob' => $blob, 'mime' => 'text/plain', 'size' => 5,
        ])->save();

        return $f;
    }

    public function test_permanent_delete_keeps_a_blob_shared_by_another_file(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'bytes');

        $a = $this->file($u, $blob);
        $b = $this->file($u, $blob); // shares the same blob (duplicate)

        // Permanently delete A: its bytes are still referenced by B → kept.
        $this->actingAs($u)->postJson(route('files.trash'), ['file_ids' => [$a->id], 'permanent' => true])->assertOk();
        Storage::disk('files')->assertExists('files/'.$blob);
        $this->assertNull(StoredFile::withoutGlobalScopes()->withTrashed()->find($a->id));
        $this->assertNotNull(StoredFile::withoutGlobalScopes()->find($b->id));

        // Now permanently delete B too: nothing references the blob → freed.
        $this->actingAs($u)->postJson(route('files.trash'), ['file_ids' => [$b->id], 'permanent' => true])->assertOk();
        Storage::disk('files')->assertMissing('files/'.$blob);
    }

    public function test_permanent_delete_keeps_a_blob_still_referenced_by_a_version(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();
        $shared = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$shared, 'v1');

        // File A currently points at $shared; File B keeps $shared as an old version.
        $a = $this->file($u, $shared);
        $b = $this->file($u, (string) Str::uuid());
        FileVersion::create(['id' => (string) Str::uuid(), 'file_id' => $b->id, 'user_id' => $u->id,
            'name' => 'b', 'mime' => 'text/plain', 'size' => 2, 'blob' => $shared, 'created_at' => now()]);

        // Permanently delete A: the blob is still held by B's version → kept.
        $this->actingAs($u)->postJson(route('files.trash'), ['file_ids' => [$a->id], 'permanent' => true])->assertOk();
        Storage::disk('files')->assertExists('files/'.$shared);
    }

    public function test_delete_blob_rejects_another_users_staged_blob(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'staged');
        FileBlob::create(['blob' => $blob, 'user_id' => $owner->id, 'created_at' => now()]);

        // Attacker cannot delete a blob staged by another user.
        $this->actingAs($attacker)->deleteJson('/files/blob/'.$blob)->assertStatus(403);
        Storage::disk('files')->assertExists('files/'.$blob);
    }

    public function test_delete_blob_refuses_a_blob_referenced_by_a_version(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'v');
        FileBlob::create(['blob' => $blob, 'user_id' => $u->id, 'created_at' => now()]);
        $f = $this->file($u, (string) Str::uuid());
        FileVersion::create(['id' => (string) Str::uuid(), 'file_id' => $f->id, 'user_id' => $u->id,
            'name' => 'v', 'mime' => 'text/plain', 'size' => 1, 'blob' => $blob, 'created_at' => now()]);

        $this->actingAs($u)->deleteJson('/files/blob/'.$blob)->assertStatus(409);
        Storage::disk('files')->assertExists('files/'.$blob);
    }
}
