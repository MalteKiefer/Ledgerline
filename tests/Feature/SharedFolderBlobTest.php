<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\SharedFolderBlob;
use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedFolderBlobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_ledger_row_persists_and_cascades_on_vault_delete(): void
    {
        $owner = User::factory()->create();
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->kind = 'folder';
        $vault->save();

        $blob = (string) Str::uuid();
        SharedFolderBlob::create(['blob' => $blob, 'vault_id' => $vault->id, 'owner_id' => $owner->id, 'size' => 42, 'created_at' => now()]);
        $this->assertNotNull(SharedFolderBlob::find($blob));

        $vault->delete();
        $this->assertNull(SharedFolderBlob::find($blob));
    }

    private function folderVaultWith(User $owner, ?User $member = null, string $role = 'editor'): SharedVault
    {
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->kind = 'folder';
        $vault->save();
        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);
        SharedVaultMember::create(['vault_id' => $vault->id, 'user_id' => $owner->id, 'role' => 'manager', 'status' => 'active', 'wrapped_vault_key' => 'wk']);
        if ($member) {
            SharedVaultMember::create(['vault_id' => $vault->id, 'user_id' => $member->id, 'role' => $role, 'status' => 'active', 'wrapped_vault_key' => 'wk']);
        }

        return $vault;
    }

    public function test_editor_can_upload_and_member_can_read(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $vault = $this->folderVaultWith($owner, $editor, 'editor');

        $blob = $this->actingAs($editor)->post(route('vaults.blobs.upload', $vault), [
            'file' => UploadedFile::fake()->create('blob.enc', 8),
        ])->assertCreated()->json('id');

        // Owner is the quota owner regardless of who uploaded.
        $row = SharedFolderBlob::find($blob);
        $this->assertSame($owner->id, (int) $row->owner_id);
        $this->assertSame($vault->id, $row->vault_id);

        Storage::disk(config('files.disk'))->assertExists('shared-folders/'.$blob);
        $this->actingAs($editor)->get(route('vaults.blobs.raw', ['vault' => $vault->id, 'blob' => $blob]))->assertOk();
    }

    public function test_viewer_cannot_upload(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $vault = $this->folderVaultWith($owner, $viewer, 'viewer');

        $this->actingAs($viewer)->post(route('vaults.blobs.upload', $vault), [
            'file' => UploadedFile::fake()->create('blob.enc', 8),
        ])->assertNotFound(); // policy denyAsNotFound
    }

    public function test_non_member_cannot_read_or_upload(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $vault = $this->folderVaultWith($owner);
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('shared-folders/'.$blob, 'x');
        SharedFolderBlob::create(['blob' => $blob, 'vault_id' => $vault->id, 'owner_id' => $owner->id, 'size' => 1, 'created_at' => now()]);

        $this->actingAs($stranger)->get(route('vaults.blobs.raw', ['vault' => $vault->id, 'blob' => $blob]))->assertNotFound();
        $this->actingAs($stranger)->post(route('vaults.blobs.upload', $vault), ['file' => UploadedFile::fake()->create('b.enc', 1)])->assertNotFound();
    }

    public function test_upload_is_rejected_over_owner_quota(): void
    {
        config(['files.quota_mb' => 1]);
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $vault = $this->folderVaultWith($owner, $editor, 'editor');
        // Owner already fills their 1 MiB quota with personal files.
        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $owner->id, 'size' => 1024 * 1024, 'created_at' => now()]);

        $this->actingAs($editor)->post(route('vaults.blobs.upload', $vault), [
            'file' => UploadedFile::fake()->create('b.enc', 4),
        ])->assertStatus(413);
    }

    public function test_reconcile_is_vault_scoped(): void
    {
        $owner = User::factory()->create();
        $vault = $this->folderVaultWith($owner);
        $disk = Storage::disk(config('files.disk'));
        $live = (string) Str::uuid();
        $orphan = (string) Str::uuid();
        foreach ([$live, $orphan] as $b) {
            $disk->put('shared-folders/'.$b, 'x');
        }
        SharedFolderBlob::create(['blob' => $live, 'vault_id' => $vault->id, 'owner_id' => $owner->id, 'size' => 5, 'created_at' => now()->subDays(3)]);
        SharedFolderBlob::create(['blob' => $orphan, 'vault_id' => $vault->id, 'owner_id' => $owner->id, 'size' => 5, 'created_at' => now()->subDays(3)]);

        $this->actingAs($owner)->postJson(route('vaults.blobs.reconcile', $vault), ['blobs' => [$live]])->assertOk();
        $this->assertNotNull(SharedFolderBlob::find($live));
        $this->assertNull(SharedFolderBlob::find($orphan));
    }
}
