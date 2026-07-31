<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function seedFolder(User $owner, string $name, ?int $parent = null): FileFolder
    {
        $folder = new FileFolder;
        $folder->forceFill(['user_id' => $owner->id, 'name' => $name, 'parent_id' => $parent]);
        $folder->save();

        return $folder;
    }

    private function seedFile(User $owner, ?int $folderId, string $name, string $content): FileEntry
    {
        $path = 'files/'.Str::uuid()->toString();
        Storage::disk(config('files.disk'))->put($path, $content);
        $file = new FileEntry;
        $file->forceFill([
            'user_id' => $owner->id,
            'file_folder_id' => $folderId,
            'name' => $name,
            'mime' => 'text/plain',
            'size' => strlen($content),
            'storage_path' => $path,
            'sha256' => hash('sha256', $content),
        ]);
        $file->save();

        return $file;
    }

    /**
     * @return array{owner: User, root: FileFolder, sub: FileFolder, f1: FileEntry, f2: FileEntry, outside: FileEntry, share: FileShare}
     */
    private function seedFolderShare(?string $password = 'secret', bool $allowDownload = true): array
    {
        $owner = User::factory()->create();
        $root = $this->seedFolder($owner, 'Shared');
        $sub = $this->seedFolder($owner, 'Sub', $root->id);
        $other = $this->seedFolder($owner, 'Private');

        $f1 = $this->seedFile($owner, $root->id, 'a.txt', 'alpha bytes');
        $f2 = $this->seedFile($owner, $sub->id, 'b.txt', 'beta bytes');
        $outside = $this->seedFile($owner, $other->id, 'c.txt', 'outside bytes');

        $share = new FileShare;
        $share->forceFill([
            'user_id' => $owner->id,
            'token' => Str::random(48),
            'kind' => 'folder',
            'file_folder_id' => $root->id,
            'password_hash' => $password !== null ? Hash::make($password) : null,
            'allow_download' => $allowDownload,
        ]);
        $share->save();

        return compact('owner', 'root', 'sub', 'f1', 'f2', 'outside', 'share');
    }

    public function test_meta_unlock_manifest_and_stream_within_subtree(): void
    {
        ['share' => $share, 'sub' => $sub, 'f2' => $f2] = $this->seedFolderShare();
        $token = $share->token;

        // meta: 200, password required, locked
        $this->get(route('public.file-share.meta', $token))->assertOk()
            ->assertJson(['found' => true, 'kind' => 'folder', 'name' => 'Shared', 'needsPassword' => true, 'unlocked' => false, 'allowDownload' => true]);

        // manifest before unlock: 403
        $this->get(route('public.file-share.manifest', $token))->assertForbidden();

        // unlock with the correct password
        $this->post(route('public.file-share.unlock', $token), ['password' => 'secret'])->assertOk()
            ->assertJson(['ok' => true]);

        // manifest lists the subtree (root + sub folder, both files)
        $manifest = $this->get(route('public.file-share.manifest', $token))->assertOk()->json();
        $this->assertSame('folder', $manifest['kind']);
        $fileIds = array_column($manifest['files'], 'id');
        $this->assertContains($f2->id, $fileIds);
        $this->assertCount(2, $manifest['files']);
        $folderIds = array_column($manifest['folders'], 'id');
        $this->assertContains($sub->id, $folderIds);

        // stream a file within the subtree → 200 + sandbox header
        $res = $this->get(route('public.file-share.file.raw', ['token' => $token, 'file' => $f2->id]))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertSame('beta bytes', $res->streamedContent());
    }

    public function test_file_outside_the_share_is_not_streamable(): void
    {
        ['share' => $share, 'outside' => $outside] = $this->seedFolderShare(password: null);
        $this->get(route('public.file-share.file.raw', ['token' => $share->token, 'file' => $outside->id]))
            ->assertNotFound();
    }

    public function test_wrong_password_is_denied(): void
    {
        ['share' => $share] = $this->seedFolderShare();
        $this->post(route('public.file-share.unlock', $share->token), ['password' => 'nope'])
            ->assertStatus(422)->assertJson(['ok' => false]);
        // Still locked afterwards.
        $this->get(route('public.file-share.manifest', $share->token))->assertForbidden();
    }

    public function test_expired_share_is_not_found(): void
    {
        ['share' => $share, 'f1' => $f1] = $this->seedFolderShare(password: null);
        $share->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get(route('public.file-share.meta', $share->token))->assertNotFound();
        $this->get(route('public.file-share.manifest', $share->token))->assertNotFound();
        $this->get(route('public.file-share.file.raw', ['token' => $share->token, 'file' => $f1->id]))->assertNotFound();
    }

    public function test_single_file_share_exposes_only_that_file(): void
    {
        $owner = User::factory()->create();
        $shared = $this->seedFile($owner, null, 'one.txt', 'just me');
        $stranger = $this->seedFile($owner, null, 'two.txt', 'not shared');

        $share = new FileShare;
        $share->forceFill(['user_id' => $owner->id, 'token' => Str::random(48), 'kind' => 'file', 'file_id' => $shared->id, 'allow_download' => false]);
        $share->save();

        $this->get(route('public.file-share.meta', $share->token))->assertOk()
            ->assertJson(['kind' => 'file', 'name' => 'one.txt', 'needsPassword' => false, 'allowDownload' => false]);

        $manifest = $this->get(route('public.file-share.manifest', $share->token))->assertOk()->json();
        $this->assertCount(1, $manifest['files']);
        $this->assertSame($shared->id, $manifest['files'][0]['id']);

        // inline view works…
        $this->get(route('public.file-share.file.raw', ['token' => $share->token, 'file' => $shared->id]))->assertOk();
        // …but a download is refused when allow_download is false.
        $this->get(route('public.file-share.file.raw', ['token' => $share->token, 'file' => $shared->id, 'download' => 1]))->assertForbidden();
        // the sibling file is not exposed.
        $this->get(route('public.file-share.file.raw', ['token' => $share->token, 'file' => $stranger->id]))->assertNotFound();
    }

    public function test_owner_can_create_update_and_revoke(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $folder = $this->seedFolder($owner, 'Docs');

        $created = $this->postJson(route('files.rel.shares.store'), [
            'kind' => 'folder', 'file_folder_id' => $folder->id, 'password' => 'pw', 'allow_download' => true,
        ])->assertCreated()->json('share');

        $this->assertSame('folder', $created['kind']);
        $this->assertTrue($created['needs_password']);
        $this->assertArrayNotHasKey('password_hash', $created);
        $id = (int) $created['id'];

        $this->assertSame(0, $created['version']);

        // stale version → 409
        $this->putJson(route('files.rel.shares.update', $id), ['allow_download' => false, 'version' => 999])
            ->assertStatus(409)->assertJson(['error' => 'version_conflict']);

        // valid update bumps version + removes password
        $updated = $this->putJson(route('files.rel.shares.update', $id), ['allow_download' => false, 'remove_password' => true, 'version' => $created['version']])
            ->assertOk()->json('share');
        $this->assertFalse($updated['allow_download']);
        $this->assertFalse($updated['needs_password']);
        $this->assertSame($created['version'] + 1, $updated['version']);

        // revoke
        $this->deleteJson(route('files.rel.shares.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('file_shares', ['id' => $id]);
    }

    public function test_crud_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($owner);
        $folder = $this->seedFolder($owner, 'Docs');
        $id = (int) $this->postJson(route('files.rel.shares.store'), ['kind' => 'folder', 'file_folder_id' => $folder->id])
            ->assertCreated()->json('share.id');

        // A foreign user cannot update it (owner scope → 404).
        $this->actingAs($stranger);
        $this->putJson(route('files.rel.shares.update', $id), ['allow_download' => false])->assertNotFound();

        // …nor destroy it (the row survives a foreign delete).
        $this->deleteJson(route('files.rel.shares.destroy', $id))->assertOk();
        $this->assertDatabaseHas('file_shares', ['id' => $id]);
    }
}
