<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FilePublicLink;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilePublicLinkTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u): StoredFile
    {
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'hello');
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'a.txt', 'blob' => $blob, 'size' => 5, 'mime' => 'text/plain'])->save();

        return $f;
    }

    public function test_create_and_public_download(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u);

        $res = $this->actingAs($u)->postJson(route('files.public-link.store', $f->id), [])->assertOk();
        $token = $res->json('token');
        $this->assertNotEmpty($token);

        // Public (guest) download works.
        $this->get(route('file-link.download', $token))->assertOk();
        $this->assertSame(1, FilePublicLink::where('token', $token)->first()->downloads);
    }

    public function test_password_gate(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u);
        $this->actingAs($u)->postJson(route('files.public-link.store', $f->id), ['password' => 'secret1'])->assertOk();
        $token = FilePublicLink::first()->token;

        // Without unlock: password page (200 with the prompt), not the file.
        $this->get(route('file-link.download', $token))->assertOk()->assertSee(__('shares.public_password_prompt'));
        // Wrong password stays on the page.
        $this->post(route('file-link.unlock', $token), ['password' => 'nope'])->assertOk();
        // Correct password unlocks then redirects to download.
        $this->post(route('file-link.unlock', $token), ['password' => 'secret1'])->assertRedirect(route('file-link.download', $token));
    }

    public function test_cannot_link_another_users_file(): void
    {
        Storage::fake('files');
        $me = User::factory()->create();
        $other = User::factory()->create();
        $theirs = $this->file($other);
        $this->actingAs($me)->postJson(route('files.public-link.store', $theirs->id), [])->assertNotFound();
    }
}
