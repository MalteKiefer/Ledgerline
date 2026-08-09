<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\FolderNode;
use App\Dav\WebDavAuth;
use App\Http\Controllers\WebDavController;
use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Sabre\HTTP\Response as SabreResponse;
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

    public function test_get_response_is_sandboxed_and_risky_types_forced_to_download(): void
    {
        // A stored HTML file (client-influenced Content-Type) must NOT be served
        // as runnable same-origin content: nosniff + empty-sandbox CSP always,
        // and a neutral type + attachment disposition for executable media types.
        $html = new SabreResponse(200);
        $html->setHeader('Content-Type', 'text/html; charset=utf-8');
        WebDavController::hardenGetResponse($html);
        $this->assertSame('nosniff', $html->getHeader('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'; sandbox", $html->getHeader('Content-Security-Policy'));
        $this->assertSame('application/octet-stream', $html->getHeader('Content-Type'));
        $this->assertSame('attachment', $html->getHeader('Content-Disposition'));

        foreach (['image/svg+xml', 'application/xhtml+xml', 'text/xml', 'application/javascript'] as $risky) {
            $r = new SabreResponse(200);
            $r->setHeader('Content-Type', $risky);
            WebDavController::hardenGetResponse($r);
            $this->assertSame('application/octet-stream', $r->getHeader('Content-Type'), "{$risky} must be neutralised");
            $this->assertSame('attachment', $r->getHeader('Content-Disposition'));
        }

        // A benign type stays inline but still carries nosniff + the sandbox CSP.
        $png = new SabreResponse(200);
        $png->setHeader('Content-Type', 'image/png');
        WebDavController::hardenGetResponse($png);
        $this->assertSame('image/png', $png->getHeader('Content-Type'));
        $this->assertNull($png->getHeader('Content-Disposition'));
        $this->assertSame('nosniff', $png->getHeader('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'; sandbox", $png->getHeader('Content-Security-Policy'));
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
