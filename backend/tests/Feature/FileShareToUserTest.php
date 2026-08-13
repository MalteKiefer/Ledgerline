<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SharedFolderController;
use App\Http\Controllers\SharedWithMeController;
use App\Models\FileEntry;
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
 * Plaintext cross-user sharing of a SINGLE FILE (folder_shares.file_id). Mirrors
 * FolderShareTest but for a lone file share: the owner shares one of their files
 * with a registered user, who then sees + downloads exactly that one file. Upload
 * is N/A (no folder), and a member may never delete the owner's lone shared file.
 *
 * As in FolderShareTest, the greedy GET SPA catch-all (Route::get('/{any}'),
 * registered at bootstrap) would otherwise shadow the ad-hoc GET /t/* routes; the
 * route table is isolated so the controllers + policy + models are proven
 * end-to-end without touching routes/*.
 */
class FileShareToUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));

        $this->app->make('router')->setRoutes(new RouteCollection);

        // Owner side.
        Route::post('/t/shared', [SharedFolderController::class, 'store']);

        // Member side.
        Route::get('/t/with-me', [SharedWithMeController::class, 'index']);
        Route::get('/t/with-me/{share}', [SharedWithMeController::class, 'browse'])->whereNumber('share');
        Route::get('/t/with-me/{share}/file/{file}/raw', [SharedWithMeController::class, 'raw'])->whereNumber(['share', 'file']);
        Route::post('/t/with-me/{share}/upload', [SharedWithMeController::class, 'upload'])->whereNumber('share');
        Route::put('/t/with-me/{share}/file/{file}', [SharedWithMeController::class, 'rename'])->whereNumber(['share', 'file']);
        Route::delete('/t/with-me/{share}/file/{file}', [SharedWithMeController::class, 'destroy'])->whereNumber(['share', 'file']);
    }

    private function seedFile(User $owner, string $name, string $content): FileEntry
    {
        $path = 'files/'.Str::uuid()->toString();
        Storage::disk(config('files.disk'))->put($path, $content);
        $file = new FileEntry;
        $file->forceFill([
            'user_id' => $owner->id,
            'file_folder_id' => null,
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
     * Owner with a shared file, a second private file, and a member on the share.
     *
     * @return array{owner: User, member: User, file: FileEntry, other: FileEntry, share: FolderShare}
     */
    private function seedFileShare(string $role = 'viewer'): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $file = $this->seedFile($owner, 'report.txt', 'shared bytes');
        $other = $this->seedFile($owner, 'secret.txt', 'private bytes');

        $share = new FolderShare;
        $share->forceFill(['owner_id' => $owner->id, 'file_id' => $file->id]);
        $share->save();
        $m = new FolderShareMember;
        $m->forceFill(['folder_share_id' => $share->id, 'user_id' => $member->id, 'role' => $role]);
        $m->save();

        return compact('owner', 'member', 'file', 'other', 'share');
    }

    public function test_owner_can_share_a_file_with_a_registered_user(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $file = $this->seedFile($owner, 'invoice.pdf', 'pdf');

        $res = $this->actingAs($owner)->postJson('/t/shared', [
            'kind' => 'file',
            'file_id' => $file->id,
            'email' => $recipient->email,
            'role' => 'viewer',
        ]);

        $res->assertCreated()
            ->assertJsonPath('share.kind', 'file')
            ->assertJsonPath('share.file_id', $file->id)
            ->assertJsonPath('share.file_name', 'invoice.pdf')
            ->assertJsonPath('share.file_folder_id', null)
            ->assertJsonPath('share.members.0.email', $recipient->email);

        $this->assertDatabaseHas('folder_shares', ['owner_id' => $owner->id, 'file_id' => $file->id]);
        $this->assertDatabaseHas('folder_share_members', ['user_id' => $recipient->id, 'role' => 'viewer']);
    }

    public function test_share_file_to_unknown_email_or_self_is_422(): void
    {
        $owner = User::factory()->create();
        $file = $this->seedFile($owner, 'a.txt', 'x');

        $this->actingAs($owner)->postJson('/t/shared', [
            'kind' => 'file', 'file_id' => $file->id, 'email' => 'nobody@example.test', 'role' => 'viewer',
        ])->assertStatus(422)->assertJsonPath('error', 'recipient_not_found');

        $this->actingAs($owner)->postJson('/t/shared', [
            'kind' => 'file', 'file_id' => $file->id, 'email' => $owner->email, 'role' => 'editor',
        ])->assertStatus(422)->assertJsonPath('error', 'recipient_not_found');

        $this->assertDatabaseCount('folder_shares', 0);
    }

    public function test_cannot_share_a_file_you_do_not_own(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $recipient = User::factory()->create();
        $strangersFile = $this->seedFile($stranger, 'theirs.txt', 'x');

        // The exists rule is owner-scoped, so the request is rejected before any
        // row is written — the file is never shared. Nothing written is the
        // deterministic security invariant (the real api group returns 422 JSON;
        // this middleware-less harness route redirects the validation failure).
        $this->actingAs($owner)->postJson('/t/shared', [
            'kind' => 'file', 'file_id' => $strangersFile->id, 'email' => $recipient->email, 'role' => 'viewer',
        ]);
        $this->assertDatabaseCount('folder_shares', 0);
        $this->assertDatabaseCount('folder_share_members', 0);
    }

    public function test_member_browses_and_downloads_the_one_file(): void
    {
        $s = $this->seedFileShare('viewer');

        $this->actingAs($s['member'])->getJson('/t/with-me/'.$s['share']->id)
            ->assertOk()
            ->assertJsonPath('kind', 'file')
            ->assertJsonPath('role', 'viewer')
            ->assertJsonPath('file.id', $s['file']->id)
            ->assertJsonPath('file.name', 'report.txt')
            ->assertJsonMissingPath('folders');

        // The shared file streams.
        $this->actingAs($s['member'])->get('/t/with-me/'.$s['share']->id.'/file/'.$s['file']->id.'/raw')
            ->assertOk();

        // It also appears in the member's "shared with me" index as a file entry.
        $this->actingAs($s['member'])->getJson('/t/with-me')
            ->assertOk()
            ->assertJsonPath('shares.0.kind', 'file')
            ->assertJsonPath('shares.0.file_name', 'report.txt');
    }

    public function test_member_cannot_reach_a_different_file_via_a_file_share(): void
    {
        $s = $this->seedFileShare('viewer');

        // The owner's OTHER file is not the shared one → 404 (existence hidden).
        $this->actingAs($s['member'])->getJson('/t/with-me/'.$s['share']->id.'/file/'.$s['other']->id.'/raw')
            ->assertNotFound();
    }

    public function test_non_member_cannot_see_the_file_share(): void
    {
        $s = $this->seedFileShare('viewer');
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson('/t/with-me/'.$s['share']->id)->assertNotFound();
        $this->actingAs($stranger)->getJson('/t/with-me')->assertOk()->assertJsonCount(0, 'shares');
    }

    public function test_editor_can_rename_but_no_one_can_delete_or_upload(): void
    {
        $s = $this->seedFileShare('editor');

        // Rename is allowed for an editor.
        $this->actingAs($s['member'])->putJson('/t/with-me/'.$s['share']->id.'/file/'.$s['file']->id, [
            'name' => 'renamed.txt',
        ])->assertOk()->assertJsonPath('file.name', 'renamed.txt');

        // Deleting the owner's lone shared file is forbidden even for an editor.
        $this->actingAs($s['member'])->deleteJson('/t/with-me/'.$s['share']->id.'/file/'.$s['file']->id)
            ->assertStatus(403);
        $this->assertDatabaseHas('files', ['id' => $s['file']->id, 'deleted_at' => null]);

        // Upload has no folder to target on a file share → 422.
        $this->actingAs($s['member'])->post('/t/with-me/'.$s['share']->id.'/upload', [
            'file' => FakeFile::fake()->createWithContent('new.txt', 'hello'),
        ])->assertStatus(422);
        $this->assertDatabaseMissing('files', ['name' => 'new.txt']);
    }

    public function test_owner_scope_isolation_for_file_shares(): void
    {
        $s = $this->seedFileShare('viewer');
        $otherOwner = User::factory()->create();

        // A different owner sees nothing shared with them and cannot browse this share.
        $this->actingAs($otherOwner)->getJson('/t/with-me')->assertOk()->assertJsonCount(0, 'shares');
        $this->actingAs($otherOwner)->getJson('/t/with-me/'.$s['share']->id)->assertNotFound();

        $this->assertDatabaseHas('folder_shares', ['id' => $s['share']->id, 'file_id' => $s['file']->id]);
    }
}
