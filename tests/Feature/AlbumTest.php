<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_an_album_and_adds_their_photos(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);
        $photo = Photo::factory()->create();

        $id = $this->postJson(route('gallery.albums.store'), ['name' => 'Trip'])->assertCreated()->json('id');
        $this->postJson(route('gallery.albums.photos.add', $id), ['photo_ids' => [$photo->id]])->assertOk();

        $album = Album::withoutGlobalScopes()->findOrFail($id);
        $this->assertTrue($album->photos()->whereKey($photo->id)->exists());
        $this->assertSame($photo->id, $album->fresh()->cover_photo_id); // cover auto-set

        $this->getJson(route('gallery.albums.show.data', $id))->assertOk()
            ->assertJsonPath('album.owned', true)->assertJsonCount(1, 'photos');
    }

    public function test_cannot_add_another_users_photo(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($bob);
        $bobPhoto = Photo::factory()->create();
        $this->app['auth']->forgetGuards();

        $this->actingAs($alice);
        $id = $this->postJson(route('gallery.albums.store'), ['name' => 'Mine'])->json('id');
        $this->postJson(route('gallery.albums.photos.add', $id), ['photo_ids' => [$bobPhoto->id]])->assertOk();

        $this->assertSame(0, Album::withoutGlobalScopes()->findOrFail($id)->photos()->count());
    }

    public function test_album_can_be_shared_internally(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice);
        $id = $this->postJson(route('gallery.albums.store'), ['name' => 'Shared'])->json('id');

        $this->postJson(route('shares.store'), ['type' => 'albums', 'id' => $id, 'email' => $bob->email, 'permission' => 'read'])->assertCreated();

        $this->actingAs($bob);
        $this->assertNotNull(Album::find($id)); // visible via owned-or-shared scope
    }

    public function test_album_public_link_is_html_openable_without_auth(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);
        $photo = Photo::factory()->create();
        $id = $this->postJson(route('gallery.albums.store'), ['name' => 'Public'])->json('id');
        $this->postJson(route('gallery.albums.photos.add', $id), ['photo_ids' => [$photo->id]]);

        $url = $this->postJson(route('public-share.store'), ['type' => 'albums', 'id' => $id])->assertCreated()->json('url');
        $this->assertStringContainsString('/album', $url);

        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertSee('Public');
    }
}
