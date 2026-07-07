<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\UploadLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_and_visitor_uploads_into_folder(): void
    {
        Storage::fake('files');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $folder = new FileFolder;
        $folder->forceFill(['id' => (string) Str::uuid(), 'user_id' => $owner->id, 'parent_id' => null, 'name' => 'Inbox'])->save();

        $res = $this->postJson(route('files.upload-links.store'), [
            'folder_id' => $folder->id, 'extensions' => 'pdf, PNG', 'label' => 'Send me',
        ])->assertOk();
        $token = $res->json('token');
        $this->assertContains('pdf', $res->json('extensions'));

        // Visitor (guest) uploads an allowed file.
        $this->app['auth']->forgetGuards();
        $this->post(route('upload-link.upload', $token), [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertOk()->assertJson(['ok' => true]);

        $stored = StoredFile::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($owner->id, (int) $stored->user_id);
        $this->assertSame($folder->id, $stored->file_folder_id);
        Storage::disk('files')->assertExists('files/'.$stored->blob);
        $this->assertSame(1, UploadLink::first()->uploads);
    }

    public function test_type_restriction_and_expiry_enforced(): void
    {
        Storage::fake('files');
        $owner = User::factory()->create();
        $link = new UploadLink;
        $link->forceFill(['token' => 'tok1', 'user_id' => $owner->id, 'file_folder_id' => null,
            'allowed_extensions' => 'pdf', 'expires_at' => null])->save();

        // Wrong type → 422.
        $this->post(route('upload-link.upload', 'tok1'), [
            'file' => UploadedFile::fake()->create('evil.exe', 5),
        ])->assertStatus(422);

        // Expired link → 410.
        $link->forceFill(['expires_at' => now()->subDay()])->save();
        $this->post(route('upload-link.upload', 'tok1'), [
            'file' => UploadedFile::fake()->create('ok.pdf', 5, 'application/pdf'),
        ])->assertStatus(410);
    }

    public function test_password_gate(): void
    {
        Storage::fake('files');
        $owner = User::factory()->create();
        $link = new UploadLink;
        $link->forceFill(['token' => 'tok2', 'user_id' => $owner->id, 'file_folder_id' => null, 'password' => bcrypt('secret1')])->save();

        // Upload without unlock → 403.
        $this->post(route('upload-link.upload', 'tok2'), [
            'file' => UploadedFile::fake()->create('a.txt', 2),
        ])->assertStatus(403);

        // Show shows the password prompt.
        $this->get(route('upload-link.show', 'tok2'))->assertOk()->assertSee(__('shares.public_password_prompt'));
    }

    public function test_cannot_target_another_users_folder(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $theirs = new FileFolder;
        $theirs->forceFill(['id' => (string) Str::uuid(), 'user_id' => $other->id, 'parent_id' => null, 'name' => 'X'])->save();

        $this->actingAs($me);
        $this->postJson(route('files.upload-links.store'), ['folder_id' => $theirs->id]);
        // Security: no link is created targeting another user's folder.
        $this->assertSame(0, UploadLink::withoutGlobalScopes()->count());
    }
}
