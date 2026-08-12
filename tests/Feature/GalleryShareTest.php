<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryInternalShare;
use App\Models\GalleryPhoto;
use App\Models\GalleryPublicShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gallery sharing: public album links (token, download gate, expiry, password)
 * and internal cross-user shares (album / whole gallery, viewer-only).
 */
class GalleryShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /** Upload one real image into a new album; return [owner, album, photo]. */
    private function seedAlbum(User $owner): array
    {
        $this->actingAs($owner);
        $id = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('a.jpg', 120, 90)])->json('photo.id');
        $album = new GalleryAlbum;
        $album->forceFill(['user_id' => $owner->id, 'name' => 'Trip'])->save();
        $album->photos()->attach($id);

        return [$owner, $album, GalleryPhoto::findOrFail($id)];
    }

    private function publicShare(User $owner, GalleryAlbum $album, array $attrs = []): GalleryPublicShare
    {
        $s = new GalleryPublicShare;
        $s->forceFill(array_merge([
            'user_id' => $owner->id, 'gallery_album_id' => $album->id, 'token' => 'tok'.str_repeat('A', 12),
            'allow_download' => false,
        ], $attrs))->save();

        return $s;
    }

    public function test_public_meta_and_manifest_are_anonymous(): void
    {
        [$owner, $album] = $this->seedAlbum(User::factory()->create());
        $share = $this->publicShare($owner, $album);
        $this->app['auth']->forgetGuards();

        $this->getJson(route('public.gallery-share.meta', ['token' => $share->token]))
            ->assertOk()->assertJsonPath('found', true)->assertJsonPath('name', 'Trip')->assertJsonPath('count', 1)->assertJsonPath('allowDownload', false);
        $this->getJson(route('public.gallery-share.manifest', ['token' => $share->token]))
            ->assertOk()->assertJsonCount(1, 'photos');
    }

    public function test_public_download_is_gated_by_allow_download(): void
    {
        [$owner, $album, $photo] = $this->seedAlbum(User::factory()->create());
        $share = $this->publicShare($owner, $album, ['allow_download' => false]);
        $this->app['auth']->forgetGuards();

        // Inline view is allowed; an explicit download is refused when disabled.
        $this->get(route('public.gallery-share.photo.raw', ['token' => $share->token, 'photo' => $photo->id]))->assertOk();
        $this->get(route('public.gallery-share.photo.raw', ['token' => $share->token, 'photo' => $photo->id, 'download' => 1]))->assertForbidden();

        $share->update(['allow_download' => true]);
        $this->get(route('public.gallery-share.photo.raw', ['token' => $share->token, 'photo' => $photo->id, 'download' => 1]))->assertOk();
    }

    public function test_expired_public_share_is_404(): void
    {
        [$owner, $album] = $this->seedAlbum(User::factory()->create());
        $share = $this->publicShare($owner, $album, ['expires_at' => now()->subDay()]);
        $this->app['auth']->forgetGuards();
        $this->getJson(route('public.gallery-share.meta', ['token' => $share->token]))->assertNotFound();
    }

    public function test_password_gates_manifest_until_unlocked(): void
    {
        [$owner, $album] = $this->seedAlbum(User::factory()->create());
        $share = $this->publicShare($owner, $album, ['password_hash' => Hash::make('sesame')]);
        $this->app['auth']->forgetGuards();

        $this->getJson(route('public.gallery-share.meta', ['token' => $share->token]))
            ->assertOk()->assertJsonPath('needsPassword', true)->assertJsonPath('name', null);
        $this->getJson(route('public.gallery-share.manifest', ['token' => $share->token]))->assertForbidden();
        $this->postJson(route('public.gallery-share.unlock', ['token' => $share->token]), ['password' => 'nope'])->assertStatus(422);
        $this->postJson(route('public.gallery-share.unlock', ['token' => $share->token]), ['password' => 'sesame'])->assertOk();
        $this->getJson(route('public.gallery-share.manifest', ['token' => $share->token]))->assertOk();
    }

    public function test_owner_can_create_public_share_and_reject_foreign_album(): void
    {
        [$owner, $album] = $this->seedAlbum(User::factory()->create());
        $this->actingAs($owner)->postJson(route('gallery.shares.public.store'), ['album_id' => $album->id, 'allow_download' => true])
            ->assertCreated()->assertJsonPath('allow_download', true);
        $this->assertDatabaseCount('gallery_public_shares', 1);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->postJson(route('gallery.shares.public.store'), ['album_id' => $album->id])->assertNotFound();
    }

    public function test_internal_share_whole_gallery_reaches_recipient(): void
    {
        [$owner] = $this->seedAlbum(User::factory()->create());
        $recipient = User::factory()->create(['email' => 'friend@example.test']);

        $this->actingAs($owner)->postJson(route('gallery.shares.internal.store'), ['email' => 'friend@example.test'])->assertCreated();
        $this->actingAs($owner)->postJson(route('gallery.shares.internal.store'), ['email' => $owner->email])->assertStatus(422); // self
        $this->actingAs($owner)->postJson(route('gallery.shares.internal.store'), ['email' => 'nobody@example.test'])->assertStatus(422); // unknown

        $share = GalleryInternalShare::query()->where('recipient_id', $recipient->id)->firstOrFail();
        $this->actingAs($recipient)->getJson(route('gallery.shared.index'))->assertOk()->assertJsonPath('shares.0.count', 1);
        $this->actingAs($recipient)->getJson(route('gallery.shared.browse', ['share' => $share->id]))->assertOk()->assertJsonCount(1, 'photos');

        // A third user cannot reach the grant.
        $this->actingAs(User::factory()->create())->getJson(route('gallery.shared.browse', ['share' => $share->id]))->assertNotFound();
    }

    public function test_collaborative_album_editor_can_contribute_viewer_cannot(): void
    {
        [$owner, $album] = $this->seedAlbum(User::factory()->create());
        $recipient = User::factory()->create(['email' => 'friend@example.test']);

        // Share the album as editor.
        $this->actingAs($owner)->postJson(route('gallery.shares.internal.store'), [
            'email' => 'friend@example.test', 'album_id' => $album->id, 'role' => 'editor',
        ])->assertCreated();
        $share = GalleryInternalShare::query()->where('recipient_id', $recipient->id)->firstOrFail();
        $this->assertSame('editor', $share->role);

        // Recipient contributes a photo → lands under the OWNER, in the album.
        $this->actingAs($recipient)->post(route('gallery.shared.upload', ['share' => $share->id]), [
            'file' => UploadedFile::fake()->image('contrib.jpg', 200, 150),
        ])->assertCreated();

        $contributed = GalleryPhoto::withoutGlobalScopes()->where('name', 'contrib.jpg')->firstOrFail();
        $this->assertSame($owner->id, $contributed->user_id);
        $this->assertTrue($album->photos()->withoutGlobalScopes()->whereKey($contributed->id)->exists());
        // Album now has 2 photos for the recipient's browse view.
        $this->actingAs($recipient)->getJson(route('gallery.shared.browse', ['share' => $share->id]))->assertOk()->assertJsonCount(2, 'photos');

        // Downgrade to viewer → contribution forbidden.
        $this->actingAs($owner)->postJson(route('gallery.shares.internal.store'), [
            'email' => 'friend@example.test', 'album_id' => $album->id, 'role' => 'viewer',
        ])->assertOk();
        $this->actingAs($recipient)->post(route('gallery.shared.upload', ['share' => $share->id]), [
            'file' => UploadedFile::fake()->image('nope.jpg', 90, 90),
        ])->assertForbidden();
    }
}
