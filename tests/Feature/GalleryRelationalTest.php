<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryRelationalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /** Upload a photo and return its id (helper). */
    private function uploadPhoto(): int
    {
        return (int) $this->post(route('gallery.rel.upload'), [
            'file' => UploadedFile::fake()->image('p.jpg', 800, 600),
        ])->assertCreated()->json('photo.id');
    }

    public function test_upload_stores_row_bytes_and_renditions(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->uploadPhoto();
        $photo = GalleryPhoto::findOrFail($id);

        // Row + server-set byte metadata.
        $this->assertSame('image', $photo->kind);
        $this->assertGreaterThan(0, $photo->size);
        $this->assertSame(800, $photo->width);
        $this->assertSame(600, $photo->height);

        $disk = Storage::disk(config('files.disk'));
        $disk->assertExists($photo->storage_path);
        $this->assertSame($photo->size, $disk->size($photo->storage_path));

        // GalleryProcessor produced webp thumb + medium renditions on disk.
        $this->assertNotNull($photo->thumb_path);
        $this->assertNotNull($photo->medium_path);
        $disk->assertExists($photo->thumb_path);
        $disk->assertExists($photo->medium_path);
    }

    public function test_download_headers_and_owner_scope(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner);
        $id = $this->uploadPhoto();

        $this->get(route('gallery.rel.raw', $id))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->get(route('gallery.rel.thumb', $id))->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        // Foreign user cannot resolve the row (owner global scope) → 404.
        $this->actingAs($other)->get(route('gallery.rel.raw', $id))->assertNotFound();
    }

    public function test_favorite_toggle(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->uploadPhoto();

        $this->postJson(route('gallery.rel.toggle', $id), ['field' => 'favorite', 'value' => true])
            ->assertOk()->assertJsonPath('photo.favorite', true);
        $this->assertTrue(GalleryPhoto::findOrFail($id)->favorite);
    }

    public function test_metadata_update_and_optimistic_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->uploadPhoto();

        $this->putJson(route('gallery.rel.update', $id), [
            'description' => 'Sunset over the lake',
            'taken_at' => '2024-06-30T14:22:05Z',
            'lat' => 47.5,
            'lng' => 8.1,
            'version' => 0,
        ])->assertOk()->assertJsonPath('photo.description', 'Sunset over the lake');

        $photo = GalleryPhoto::findOrFail($id);
        $this->assertSame('2024-06-30', $photo->taken_at?->format('Y-m-d'));
        $this->assertSame('47.500000', (string) $photo->lat);

        // Stale version → 409.
        $this->putJson(route('gallery.rel.update', $id), ['description' => 'again', 'version' => 0])
            ->assertStatus(409)->assertJsonPath('error', 'version_conflict');
    }

    public function test_album_create_add_remove_and_cover(): void
    {
        $this->actingAs(User::factory()->create());
        $p1 = $this->uploadPhoto();
        $p2 = $this->uploadPhoto();

        $album = (int) $this->postJson(route('gallery.rel.albums.store'), ['name' => 'Trip'])
            ->assertCreated()->json('album.id');

        $this->postJson(route('gallery.rel.albums.photos.add', $album), ['photo_ids' => [$p1, $p2]])->assertOk();
        $this->assertDatabaseHas('gallery_album_photo', ['gallery_album_id' => $album, 'gallery_photo_id' => $p1]);
        $this->assertDatabaseHas('gallery_album_photo', ['gallery_album_id' => $album, 'gallery_photo_id' => $p2]);

        $this->deleteJson(route('gallery.rel.albums.photos.remove', ['album' => $album, 'photo' => $p1]))->assertOk();
        $this->assertDatabaseMissing('gallery_album_photo', ['gallery_album_id' => $album, 'gallery_photo_id' => $p1]);

        $this->postJson(route('gallery.rel.albums.cover', $album), ['photo_id' => $p2])
            ->assertOk()->assertJsonPath('album.cover_photo_id', $p2);
    }

    public function test_trash_restore_force_removes_all_disk_files(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->uploadPhoto();
        $paths = GalleryPhoto::findOrFail($id)->storagePaths();
        $this->assertNotEmpty($paths);

        $this->deleteJson(route('gallery.rel.destroy', $id))->assertOk();
        $this->getJson(route('gallery.rel.data'))->assertOk()->assertJsonCount(0, 'photos');
        $this->getJson(route('gallery.rel.trash'))->assertOk()->assertJsonCount(1, 'photos');

        $this->postJson(route('gallery.rel.restore', $id))->assertOk();
        $this->getJson(route('gallery.rel.data'))->assertJsonCount(1, 'photos');

        $this->deleteJson(route('gallery.rel.destroy', $id))->assertOk();
        $this->deleteJson(route('gallery.rel.force', $id))->assertOk();
        $this->assertNull(GalleryPhoto::withTrashed()->find($id));

        $disk = Storage::disk(config('files.disk'));
        foreach ($paths as $p) {
            $disk->assertMissing($p);
        }
    }

    public function test_public_share_serves_photo_bytes(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->uploadPhoto();

        $token = (string) $this->postJson(route('gallery.rel.shares.store'), [
            'kind' => 'photo',
            'gallery_photo_id' => $id,
        ])->assertCreated()->json('share.token');

        $this->get(route('public.gallery-share.photo.raw', ['token' => $token, 'photo' => $id]))
            ->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        $this->getJson(route('public.gallery-share.meta', $token))
            ->assertOk()->assertJsonPath('needsPassword', false)->assertJsonPath('unlocked', true);
    }

    public function test_password_protected_share_requires_unlock(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->uploadPhoto();

        $token = (string) $this->postJson(route('gallery.rel.shares.store'), [
            'kind' => 'photo',
            'gallery_photo_id' => $id,
            'password' => 'letmein',
        ])->assertCreated()->json('share.token');

        // Locked before unlock.
        $this->get(route('public.gallery-share.photo.raw', ['token' => $token, 'photo' => $id]))->assertForbidden();

        // Wrong password → 422; correct → ok, then bytes flow.
        $this->postJson(route('public.gallery-share.unlock', $token), ['password' => 'nope'])->assertStatus(422);
        $this->postJson(route('public.gallery-share.unlock', $token), ['password' => 'letmein'])->assertOk();
        $this->get(route('public.gallery-share.photo.raw', ['token' => $token, 'photo' => $id]))->assertOk();
    }

    public function test_quota_rejects_over_limit_upload(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['gallery_quota_mb' => 1])->save(); // 1 MiB cap
        $this->actingAs($user);

        $this->post(route('gallery.rel.upload'), ['file' => UploadedFile::fake()->create('big.bin', 2048, 'image/jpeg')])
            ->assertStatus(413)->assertJsonPath('error', 'quota');
        $this->assertSame(0, GalleryPhoto::withTrashed()->count());
    }

    public function test_photos_are_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a);
        $this->uploadPhoto();
        $this->actingAs($b)->getJson(route('gallery.rel.data'))->assertOk()->assertJsonCount(0, 'photos');
    }

    public function test_chunked_upload_assembles_bytes(): void
    {
        $this->actingAs(User::factory()->create());

        $sessionId = (string) $this->postJson(route('gallery.rel.chunk.init'), ['size' => 6, 'mime' => 'application/octet-stream'])
            ->assertCreated()->json('id');

        $this->post(route('gallery.rel.chunk.part'), ['id' => $sessionId, 'index' => 0, 'file' => UploadedFile::fake()->createWithContent('0', 'foo')])->assertOk();
        $this->post(route('gallery.rel.chunk.part'), ['id' => $sessionId, 'index' => 1, 'file' => UploadedFile::fake()->createWithContent('1', 'bar')])->assertOk();

        $id = (int) $this->postJson(route('gallery.rel.chunk.complete'), ['id' => $sessionId])->assertCreated()->json('photo.id');

        $photo = GalleryPhoto::findOrFail($id);
        $this->assertSame(6, $photo->size);
        $this->assertSame('foobar', $this->get(route('gallery.rel.raw', $id))->streamedContent());
    }
}
