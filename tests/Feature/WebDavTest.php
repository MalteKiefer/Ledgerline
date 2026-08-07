<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\FolderNode;
use App\Dav\WebDavAuth;
use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * WebDAV wiring: the app-specific password lifecycle, the Basic-auth backend, and
 * owner-scoped node listing. The full DAV protocol exchange (PROPFIND/PUT over
 * the SAPI) is verified manually against a real client, not here.
 */
class WebDavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_user_can_set_and_clear_the_webdav_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('profile.webdav.update'), ['webdav_password' => 'a-strong-dav-pass'])
            ->assertRedirect();
        $this->assertNotNull($user->fresh()->webdav_password);

        $this->actingAs($user)->put(route('profile.webdav.update'), ['webdav_password' => 'short'])
            ->assertSessionHasErrors('webdav_password');

        $this->actingAs($user)->delete(route('profile.webdav.destroy'))->assertRedirect();
        $this->assertNull($user->fresh()->webdav_password);
    }

    public function test_dav_endpoint_requires_authentication(): void
    {
        // No Basic credentials → Sabre answers 401 with a WWW-Authenticate challenge.
        $this->call('PROPFIND', '/dav/')->assertStatus(401);
    }

    public function test_auth_backend_validates_the_app_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('profile.webdav.update'), ['webdav_password' => 'a-strong-dav-pass']);
        Auth::logout();

        $backend = new WebDavAuth;
        $ok = (new \ReflectionMethod($backend, 'validateUserPass'))->invoke($backend, $user->email, 'a-strong-dav-pass');
        $this->assertTrue($ok);
        Auth::logout();
        $bad = (new \ReflectionMethod($backend, 'validateUserPass'))->invoke($backend, $user->email, 'wrong');
        $this->assertFalse($bad);
    }

    public function test_folder_node_lists_only_the_owners_files(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('mine.txt', 'x')]);
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('theirs.txt', 'y')]);

        Auth::login($owner);
        $names = array_map(fn ($n): string => $n->getName(), (new FolderNode(null))->getChildren());
        $this->assertContains('mine.txt', $names);
        $this->assertNotContains('theirs.txt', $names);
        $this->assertSame(FileEntry::query()->count(), 1); // owner-scoped
    }
}
