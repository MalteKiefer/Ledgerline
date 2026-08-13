<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SharedFolderController;
use App\Http\Controllers\SharedWithMeController;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FolderShare;
use App\Models\FolderShareMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as FakeFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Plaintext cross-user folder sharing (pivot). The real web+api routes are wired
 * by the parent; this suite registers the same controller actions locally so the
 * controllers + policy + models are proven end-to-end without touching routes/*.
 */
class FolderShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));

        // The application boots a greedy GET SPA catch-all — Route::get('/{any}')
        // in routes/web.php, registered at bootstrap, i.e. BEFORE this setUp — that
        // would shadow the ad-hoc GET /t/* routes below (Laravel matches routes in
        // registration order) and return the SPA HTML shell instead of reaching the
        // controllers, making every member-side GET assertion (browse/raw/index)
        // read 200-HTML instead of the controller's JSON/404. Isolate the route
        // table to just these controller-proving routes so authorization + subtree
        // scope are genuinely exercised. Safe: the controllers under test never call
        // route()/redirect()/url(), so no application routes are needed here.
        $this->app->make('router')->setRoutes(new RouteCollection);

        // Owner side.
        Route::get('/t/shared', [SharedFolderController::class, 'index']);
        Route::post('/t/shared', [SharedFolderController::class, 'store']);
        Route::put('/t/shared/{share}/member', [SharedFolderController::class, 'updateMember'])->whereNumber('share');
        Route::delete('/t/shared/{share}/member', [SharedFolderController::class, 'removeMember'])->whereNumber('share');
        Route::delete('/t/shared/{share}', [SharedFolderController::class, 'destroy'])->whereNumber('share');

        // Member side.
        Route::get('/t/with-me', [SharedWithMeController::class, 'index']);
        Route::get('/t/with-me/{share}', [SharedWithMeController::class, 'browse'])->whereNumber('share');
        Route::get('/t/with-me/{share}/file/{file}/raw', [SharedWithMeController::class, 'raw'])->whereNumber(['share', 'file']);
        Route::post('/t/with-me/{share}/upload', [SharedWithMeController::class, 'upload'])->whereNumber('share');
        Route::put('/t/with-me/{share}/file/{file}', [SharedWithMeController::class, 'rename'])->whereNumber(['share', 'file']);
        Route::delete('/t/with-me/{share}/file/{file}', [SharedWithMeController::class, 'destroy'])->whereNumber(['share', 'file']);
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
     * Owner with a shared "root" (root → sub) tree, a private folder outside it,
     * and a member on the share at the given role.
     *
     * @return array{owner: User, member: User, root: FileFolder, sub: FileFolder, inRoot: FileEntry, inSub: FileEntry, outside: FileEntry, share: FolderShare}
     */
    private function seedShare(string $role = 'editor'): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $root = $this->seedFolder($owner, 'Shared');
        $sub = $this->seedFolder($owner, 'Sub', $root->id);
        $private = $this->seedFolder($owner, 'Private');

        $inRoot = $this->seedFile($owner, $root->id, 'a.txt', 'alpha');
        $inSub = $this->seedFile($owner, $sub->id, 'b.txt', 'beta');
        $outside = $this->seedFile($owner, $private->id, 'c.txt', 'outside');

        $share = new FolderShare;
        $share->forceFill(['owner_id' => $owner->id, 'file_folder_id' => $root->id]);
        $share->save();
        $member->refresh();
        $m = new FolderShareMember;
        $m->forceFill(['folder_share_id' => $share->id, 'user_id' => $member->id, 'role' => $role]);
        $m->save();

        return compact('owner', 'member', 'root', 'sub', 'inRoot', 'inSub', 'outside', 'share');
    }

    public function test_owner_can_create_share_with_a_registered_user(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $folder = $this->seedFolder($owner, 'Docs');

        $res = $this->actingAs($owner)->postJson('/t/shared', [
            'file_folder_id' => $folder->id,
            'email' => $recipient->email,
            'role' => 'viewer',
        ]);

        $res->assertCreated()
            ->assertJsonPath('share.folder_name', 'Docs')
            ->assertJsonPath('share.members.0.email', $recipient->email)
            ->assertJsonPath('share.members.0.role', 'viewer');

        $this->assertDatabaseHas('folder_shares', ['owner_id' => $owner->id, 'file_folder_id' => $folder->id]);
        $this->assertDatabaseHas('folder_share_members', ['user_id' => $recipient->id, 'role' => 'viewer']);
    }

    public function test_share_to_unknown_email_or_self_is_422(): void
    {
        $owner = User::factory()->create();
        $folder = $this->seedFolder($owner, 'Docs');

        $this->actingAs($owner)->postJson('/t/shared', [
            'file_folder_id' => $folder->id,
            'email' => 'nobody@example.test',
            'role' => 'viewer',
        ])->assertStatus(422)->assertJsonPath('error', 'recipient_not_found');

        // Sharing to self yields the same unified error (no directory enumeration).
        $this->actingAs($owner)->postJson('/t/shared', [
            'file_folder_id' => $folder->id,
            'email' => $owner->email,
            'role' => 'editor',
        ])->assertStatus(422)->assertJsonPath('error', 'recipient_not_found');

        $this->assertDatabaseCount('folder_shares', 0);
    }

    public function test_member_browses_only_the_shared_subtree(): void
    {
        $s = $this->seedShare('viewer');

        $res = $this->actingAs($s['member'])->getJson('/t/with-me/'.$s['share']->id);

        $res->assertOk()->assertJsonPath('role', 'viewer');

        $folderIds = collect($res->json('folders'))->pluck('id')->all();
        $fileIds = collect($res->json('files'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$s['root']->id, $s['sub']->id], $folderIds);
        $this->assertEqualsCanonicalizing([$s['inRoot']->id, $s['inSub']->id], $fileIds);
        $this->assertNotContains($s['outside']->id, $fileIds);
    }

    public function test_member_cannot_reach_a_file_outside_the_subtree(): void
    {
        $s = $this->seedShare('editor');

        // A file the owner has but that lives outside the shared folder → 404.
        $this->actingAs($s['member'])->getJson('/t/with-me/'.$s['share']->id.'/file/'.$s['outside']->id.'/raw')
            ->assertNotFound();

        // A file inside the subtree streams fine.
        $this->actingAs($s['member'])->get('/t/with-me/'.$s['share']->id.'/file/'.$s['inRoot']->id.'/raw')
            ->assertOk();
    }

    public function test_non_member_cannot_see_the_share(): void
    {
        $s = $this->seedShare('viewer');
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson('/t/with-me/'.$s['share']->id)->assertNotFound();
        $this->actingAs($stranger)->getJson('/t/with-me')->assertOk()->assertJsonCount(0, 'shares');
    }

    public function test_viewer_cannot_upload(): void
    {
        $s = $this->seedShare('viewer');

        $this->actingAs($s['member'])->post('/t/with-me/'.$s['share']->id.'/upload', [
            'file' => FakeFile::fake()->createWithContent('new.txt', 'hello'),
        ])->assertStatus(403);

        $this->assertDatabaseMissing('files', ['name' => 'new.txt']);
    }

    public function test_editor_can_upload_into_the_shared_subtree(): void
    {
        $s = $this->seedShare('editor');

        $res = $this->actingAs($s['member'])->post('/t/with-me/'.$s['share']->id.'/upload', [
            'file' => FakeFile::fake()->createWithContent('new.txt', 'hello'),
        ]);

        $res->assertCreated()->assertJsonPath('file.name', 'new.txt');

        // The file is owned by the SHARE OWNER (not the uploading member) and lands
        // in the shared root folder.
        $this->assertDatabaseHas('files', [
            'name' => 'new.txt',
            'user_id' => $s['owner']->id,
            'file_folder_id' => $s['root']->id,
        ]);
    }

    public function test_editor_can_rename_and_delete_within_the_subtree(): void
    {
        $s = $this->seedShare('editor');

        $this->actingAs($s['member'])->putJson('/t/with-me/'.$s['share']->id.'/file/'.$s['inRoot']->id, [
            'name' => 'renamed.txt',
        ])->assertOk()->assertJsonPath('file.name', 'renamed.txt');

        $this->actingAs($s['member'])->deleteJson('/t/with-me/'.$s['share']->id.'/file/'.$s['inSub']->id)
            ->assertOk();

        $this->assertDatabaseHas('files', ['id' => $s['inRoot']->id, 'name' => 'renamed.txt']);
        $this->assertSoftDeleted('files', ['id' => $s['inSub']->id]);
    }

    public function test_remove_member_revokes_access(): void
    {
        $s = $this->seedShare('editor');

        // Member has access before removal.
        $this->actingAs($s['member'])->getJson('/t/with-me/'.$s['share']->id)->assertOk();

        $this->actingAs($s['owner'])->deleteJson('/t/shared/'.$s['share']->id.'/member', [
            'user_id' => $s['member']->id,
        ])->assertOk();

        // ...and none after.
        $this->actingAs($s['member'])->getJson('/t/with-me/'.$s['share']->id)->assertNotFound();
        $this->assertDatabaseMissing('folder_share_members', [
            'folder_share_id' => $s['share']->id,
            'user_id' => $s['member']->id,
        ]);
    }

    public function test_owner_can_change_member_role(): void
    {
        $s = $this->seedShare('viewer');

        $this->actingAs($s['owner'])->putJson('/t/shared/'.$s['share']->id.'/member', [
            'user_id' => $s['member']->id,
            'role' => 'editor',
        ])->assertOk();

        // Now the (former viewer) member can upload.
        $this->actingAs($s['member'])->post('/t/with-me/'.$s['share']->id.'/upload', [
            'file' => FakeFile::fake()->createWithContent('ok.txt', 'x'),
        ])->assertCreated();
    }

    public function test_owner_scope_isolation_across_owners(): void
    {
        $s = $this->seedShare('editor');
        $otherOwner = User::factory()->create();

        // A different owner cannot see, manage, or delete this owner's share.
        $this->actingAs($otherOwner)->getJson('/t/shared')->assertOk()->assertJsonCount(0, 'shares');
        $this->actingAs($otherOwner)->deleteJson('/t/shared/'.$s['share']->id)->assertNotFound();
        $this->actingAs($otherOwner)->putJson('/t/shared/'.$s['share']->id.'/member', [
            'user_id' => $s['member']->id,
            'role' => 'viewer',
        ])->assertNotFound();

        // The share and its member survive the rejected cross-owner calls.
        $this->assertDatabaseHas('folder_shares', ['id' => $s['share']->id, 'owner_id' => $s['owner']->id]);
        $this->assertDatabaseHas('folder_share_members', [
            'folder_share_id' => $s['share']->id, 'role' => 'editor',
        ]);
    }
}
