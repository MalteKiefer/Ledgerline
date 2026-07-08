<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * End-to-end server behaviour of the zero-knowledge Files vault: every sync
 * carries sealed metadata + a wrapped key, never a plaintext name/mime, and
 * move/rename/trash/restore/version operations must NEVER drop the wrapped key
 * or blob (that would make the file permanently undecryptable = data loss).
 */
class FilesVaultFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $this->user = User::factory()->create();
    }

    /** Put a fake ciphertext blob on disk and record it as uploaded. */
    private function uploadBlob(string $bytes = 'ciphertext'): string
    {
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, $bytes);
        FileBlob::create(['blob' => $blob, 'user_id' => $this->user->id, 'created_at' => now()]);

        return $blob;
    }

    /** @param array<int,array<string,mixed>> $files @param array<int,array<string,mixed>> $folders */
    private function sync(array $files, array $folders = []): TestResponse
    {
        return $this->actingAs($this->user)->putJson(route('files.sync'), ['folders' => $folders, 'files' => $files]);
    }

    /** A structurally-valid sealed {c,n} envelope tagged so assertions stay exact. */
    private function env(string $tag): string
    {
        return json_encode(['c' => base64_encode($tag.'-cipher-payload'), 'n' => base64_encode('nonce-1234567890-'.$tag)]);
    }

    private function fileItem(string $id, string $blob, string $meta = 'meta', string $key = 'key', ?string $folder = null): array
    {
        return ['id' => $id, 'blob' => $blob, 'enc_metadata' => $this->env($meta), 'enc_file_key' => $this->env($key), 'folder' => $folder, 'tags' => []];
    }

    public function test_a_synced_file_stores_only_sealed_fields_never_plaintext(): void
    {
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        $this->sync([$this->fileItem($id, $blob, 'META123', 'KEY123')])->assertOk();

        $row = StoredFile::withoutGlobalScopes()->find($id);
        $this->assertNull($row->name, 'plaintext name must not be stored');
        $this->assertNull($row->mime, 'plaintext mime must not be stored');
        $this->assertSame($this->env('META123'), $row->enc_metadata);
        $this->assertSame($this->env('KEY123'), $row->enc_file_key);
        $this->assertTrue((bool) $row->is_encrypted);
    }

    public function test_data_endpoint_returns_sealed_fields_and_no_plaintext(): void
    {
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        $this->sync([$this->fileItem($id, $blob, 'M', 'K')])->assertOk();

        $res = $this->actingAs($this->user)->getJson(route('files.data'))->assertOk();
        $res->assertJsonPath('files.0.enc_metadata', $this->env('M'))
            ->assertJsonPath('files.0.enc_file_key', $this->env('K'))
            ->assertJsonMissingPath('files.0.name')
            ->assertJsonMissingPath('files.0.mime');
    }

    public function test_moving_a_file_between_folders_keeps_its_key_and_blob(): void
    {
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        $folders = [['id' => $a, 'name' => 'sealedA', 'parent' => null], ['id' => $b, 'name' => 'sealedB', 'parent' => null]];

        $this->sync([$this->fileItem($id, $blob, 'M', 'K', $a)], $folders)->assertOk();
        // Move: same id/blob/key, only folder changes.
        $this->sync([$this->fileItem($id, $blob, 'M', 'K', $b)], $folders)->assertOk();

        $row = StoredFile::withoutGlobalScopes()->find($id);
        $this->assertSame($b, $row->file_folder_id);
        $this->assertSame($blob, $row->blob, 'blob must survive a move');
        $this->assertSame($this->env('K'), $row->enc_file_key, 'wrapped key must survive a move');
    }

    public function test_renaming_reseals_metadata_but_keeps_key_and_blob(): void
    {
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        $this->sync([$this->fileItem($id, $blob, 'OLDNAME', 'K')])->assertOk();
        // Rename = new sealed metadata, SAME wrapped key + blob.
        $this->sync([$this->fileItem($id, $blob, 'NEWNAME', 'K')])->assertOk();

        $row = StoredFile::withoutGlobalScopes()->find($id);
        $this->assertSame($this->env('NEWNAME'), $row->enc_metadata);
        $this->assertSame($this->env('K'), $row->enc_file_key);
        $this->assertSame($blob, $row->blob);
        // No version snapshot: the blob did not change.
        $this->assertSame(0, FileVersion::where('file_id', $id)->count());
    }

    public function test_changing_the_blob_snapshots_the_old_sealed_metadata_and_key(): void
    {
        $id = (string) Str::uuid();
        $blob1 = $this->uploadBlob('v1');
        $blob2 = $this->uploadBlob('v2');
        $this->sync([$this->fileItem($id, $blob1, 'META1', 'KEY1')])->assertOk();
        $this->sync([$this->fileItem($id, $blob2, 'META2', 'KEY2')])->assertOk();

        $version = FileVersion::where('file_id', $id)->first();
        $this->assertNotNull($version, 'a blob change must snapshot a version');
        $this->assertSame($blob1, $version->blob);
        $this->assertSame($this->env('META1'), $version->enc_metadata, 'version keeps the OLD sealed metadata');
        $this->assertSame($this->env('KEY1'), $version->enc_file_key, 'version keeps the OLD wrapped key so it stays decryptable');
        $this->assertNull($version->name);
    }

    public function test_versions_endpoint_exposes_sealed_metadata_and_key(): void
    {
        $id = (string) Str::uuid();
        $blob1 = $this->uploadBlob('v1');
        $blob2 = $this->uploadBlob('v2');
        $this->sync([$this->fileItem($id, $blob1, 'META1', 'KEY1')])->assertOk();
        $this->sync([$this->fileItem($id, $blob2, 'META2', 'KEY2')])->assertOk();

        $this->actingAs($this->user)->getJson(route('files.versions', ['file' => $id]))->assertOk()
            ->assertJsonPath('versions.0.enc_metadata', $this->env('META1'))
            ->assertJsonPath('versions.0.enc_file_key', $this->env('KEY1'))
            ->assertJsonMissingPath('versions.0.name');
    }

    public function test_trash_then_restore_keeps_the_key_and_blob(): void
    {
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        $this->sync([$this->fileItem($id, $blob, 'M', 'K')])->assertOk();
        // Trash via manifest timestamp.
        $trashed = ['id' => $id, 'blob' => $blob, 'enc_metadata' => $this->env('M'), 'enc_file_key' => $this->env('K'), 'folder' => null, 'tags' => [], 'trashed' => now()->toIso8601String()];
        $this->sync([$trashed])->assertOk();
        $this->assertSoftDeleted('files', ['id' => $id]);
        // Restore.
        $this->sync([$this->fileItem($id, $blob, 'M', 'K')])->assertOk();

        $row = StoredFile::withoutGlobalScopes()->find($id);
        $this->assertNull($row->deleted_at);
        $this->assertSame($this->env('K'), $row->enc_file_key);
        $this->assertSame($blob, $row->blob);
    }

    public function test_a_deep_nested_folder_tree_with_files_round_trips(): void
    {
        $root = (string) Str::uuid();
        $mid = (string) Str::uuid();
        $leaf = (string) Str::uuid();
        $folders = [
            ['id' => $root, 'name' => 's1', 'parent' => null],
            ['id' => $mid, 'name' => 's2', 'parent' => $root],
            ['id' => $leaf, 'name' => 's3', 'parent' => $mid],
        ];
        $ids = [];
        $files = [];
        foreach ([$root, $mid, $leaf] as $fld) {
            $fid = (string) Str::uuid();
            $ids[] = $fid;
            $files[] = $this->fileItem($fid, $this->uploadBlob(), 'M', 'K', $fld);
        }
        $this->sync($files, $folders)->assertOk();

        $this->assertSame(3, FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->count());
        $this->assertSame(3, StoredFile::withoutGlobalScopes()->count());
        $this->assertSame($mid, FileFolder::withoutGlobalScopes()->find($leaf)->parent_id);
        foreach ($ids as $fid) {
            $this->assertSame($this->env('K'), StoredFile::withoutGlobalScopes()->find($fid)->enc_file_key);
        }
    }

    public function test_a_large_multi_file_batch_syncs_and_keeps_every_key(): void
    {
        $files = [];
        for ($i = 0; $i < 50; $i++) {
            $files[] = $this->fileItem((string) Str::uuid(), $this->uploadBlob("c{$i}"), "M{$i}", "K{$i}");
        }
        $this->sync($files)->assertOk();

        $this->assertSame(50, StoredFile::withoutGlobalScopes()->count());
        // Spot-check a few kept their own distinct sealed key.
        foreach ([0, 25, 49] as $i) {
            $row = StoredFile::withoutGlobalScopes()->where('enc_metadata', $this->env("M{$i}"))->first();
            $this->assertSame($this->env("K{$i}"), $row->enc_file_key);
        }
    }

    public function test_sync_rejects_a_file_missing_its_sealed_fields(): void
    {
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        // No enc_metadata / enc_file_key → rejected before any write. The
        // data-safety invariant we care about: NO half-written, undecryptable row.
        $res = $this->withExceptionHandling()->actingAs($this->user)
            ->putJson(route('files.sync'), ['folders' => [], 'files' => [['id' => $id, 'blob' => $blob, 'folder' => null, 'tags' => []]]]);
        $this->assertGreaterThanOrEqual(300, $res->status(), 'a manifest missing sealed fields must not succeed');
        $this->assertSame(0, StoredFile::withoutGlobalScopes()->count());
    }

    public function test_moving_a_file_does_not_trip_the_blob_allow_list(): void
    {
        // Regression: a move re-sends the same blob; after the first sync the blob
        // is attached to the file, so the allow-list must still accept it (not 422).
        $id = (string) Str::uuid();
        $blob = $this->uploadBlob();
        $a = (string) Str::uuid();
        $this->sync([$this->fileItem($id, $blob, 'M', 'K')], [['id' => $a, 'name' => 's', 'parent' => null]])->assertOk();
        // The FileBlob upload record is consumed by the first sync; the second sync
        // must accept the blob because it is now attached to the user's own file.
        $this->sync([$this->fileItem($id, $blob, 'M', 'K', $a)], [['id' => $a, 'name' => 's', 'parent' => null]])->assertOk();
        $this->assertSame($a, StoredFile::withoutGlobalScopes()->find($id)->file_folder_id);
    }
}
