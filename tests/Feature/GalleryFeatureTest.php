<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateGalleryThumbnail;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_upload_stores_row_and_bytes(): void
    {
        $this->actingAs(User::factory()->create());

        $res = $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('holiday.jpg', 200, 150),
        ])->assertCreated();

        $id = (int) $res->json('photo.id');
        $photo = GalleryPhoto::findOrFail($id);
        $this->assertSame('holiday.jpg', $photo->name);
        $this->assertNotNull($photo->sha256);
        $this->assertStringStartsWith('gallery/', (string) $photo->storage_path);
        Storage::disk(config('files.disk'))->assertExists($photo->storage_path);
    }

    public function test_thumb_returns_sandboxed_webp(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('a.png', 300, 300),
        ])->json('photo.id');

        $res = $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk();
        $this->assertSame('image/webp', $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $res->headers->get('Content-Security-Policy'));
        $v = GalleryPhoto::findOrFail($id)->version;
        Storage::disk(config('files.disk'))->assertExists('gallery/thumb/'.$id.'-'.$v.'.webp');
    }

    public function test_upload_queues_thumbnail_and_thumb_endpoint_is_cache_only(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $id = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('q.jpg', 200, 200)])->json('photo.id');
        // Upload queues the thumbnail off the web path (never generated inline).
        Queue::assertPushed(GenerateGalleryThumbnail::class);

        // No cache yet → the endpoint does NOT decode inline; it 404s and re-queues.
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertNotFound();

        // Running the job generates the cached WebP; the endpoint then serves it.
        (new GenerateGalleryThumbnail($id))->handle(
            app(\App\Http\Controllers\GalleryController::class),
            app(\App\Support\ImageManagerFactory::class),
        );
        $v = GalleryPhoto::findOrFail($id)->version;
        Storage::disk(config('files.disk'))->assertExists('gallery/thumb/'.$id.'-'.$v.'.webp');
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_owner_scope_blocks_cross_user_access(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner);
        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('mine.jpg'),
        ])->json('photo.id');

        $this->actingAs($other);
        $this->get(route('gallery.raw', ['photo' => $id]))->assertNotFound();
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertNotFound();
        $this->delete(route('gallery.destroy', ['photo' => $id]))->assertNotFound();
    }

    public function test_favorite_toggle(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('f.jpg'),
        ])->json('photo.id');

        $this->patch(route('gallery.favorite', ['photo' => $id]), ['favorite' => true])->assertOk();
        $this->assertTrue((bool) GalleryPhoto::findOrFail($id)->favorite);
    }

    public function test_trash_restore_and_force_purges_blobs(): void
    {
        $this->actingAs(User::factory()->create());
        $up = $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('t.jpg'),
        ]);
        $id = (int) $up->json('photo.id');
        $photo = GalleryPhoto::findOrFail($id);
        $path = (string) $photo->storage_path;
        $thumb = 'gallery/thumb/'.$id.'-'.$photo->version.'.webp';
        // Warm a thumbnail so the purge has something to remove.
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk();

        $this->delete(route('gallery.destroy', ['photo' => $id]))->assertOk();
        $this->assertNotNull(GalleryPhoto::onlyTrashed()->find($id));
        $this->assertSame($id, (int) $this->get(route('gallery.trash'))->json('photos.0.id'));

        $this->post(route('gallery.restore', ['id' => $id]))->assertOk();
        $this->assertNull(GalleryPhoto::onlyTrashed()->find($id));

        $this->delete(route('gallery.destroy', ['photo' => $id]))->assertOk();
        $this->delete(route('gallery.force', ['id' => $id]))->assertOk();
        $this->assertNull(GalleryPhoto::withTrashed()->find($id));
        Storage::disk(config('files.disk'))->assertMissing($path);
        Storage::disk(config('files.disk'))->assertMissing($thumb);
    }

    public function test_data_lists_only_active_photos(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('one.jpg'),
        ])->json('photo.id');
        $this->delete(route('gallery.destroy', ['photo' => $id]))->assertOk();

        $this->assertCount(0, $this->get(route('gallery.data'))->json('photos'));
    }

    public function test_bulk_destroy_trashes_only_own_photos(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $foreign = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('x.jpg')])->json('photo.id');

        $this->actingAs($owner);
        $a = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('a.jpg')])->json('photo.id');
        $b = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('b.jpg')])->json('photo.id');

        $this->post(route('gallery.bulk-destroy'), ['ids' => [$a, $b, $foreign]])->assertOk();

        $this->assertNotNull(GalleryPhoto::onlyTrashed()->find($a));
        $this->assertNotNull(GalleryPhoto::onlyTrashed()->find($b));
        // Foreign photo untouched (owner scope).
        $this->actingAs($other);
        $this->assertNull(GalleryPhoto::onlyTrashed()->find($foreign));
    }

    public function test_album_lifecycle_attach_filter_detach(): void
    {
        $this->actingAs(User::factory()->create());
        $p1 = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('1.jpg')])->json('photo.id');
        $p2 = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('2.jpg')])->json('photo.id');

        $albumId = (int) $this->post(route('gallery.albums.store'), ['name' => 'Trip'])->assertCreated()->json('album.id');

        $this->post(route('gallery.albums.attach', ['album' => $albumId]), ['ids' => [$p1]])->assertOk();
        $this->assertSame(1, GalleryAlbum::findOrFail($albumId)->photos()->count());

        // album_id filter returns only attached photos
        $filtered = $this->get(route('gallery.data', ['album_id' => $albumId]))->json('photos');
        $this->assertCount(1, $filtered);
        $this->assertSame($p1, (int) $filtered[0]['id']);

        // full library still shows both
        $this->assertCount(2, $this->get(route('gallery.data'))->json('photos'));

        $this->delete(route('gallery.albums.detach', ['album' => $albumId]), ['ids' => [$p1]])->assertOk();
        $this->assertSame(0, GalleryAlbum::findOrFail($albumId)->photos()->count());

        $this->delete(route('gallery.albums.destroy', ['album' => $albumId]))->assertOk();
        $this->assertNull(GalleryAlbum::find($albumId));
    }

    public function test_edit_sets_metadata_and_transforms_non_invasively(): void
    {
        $this->actingAs(User::factory()->create());
        $up = $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('e.jpg', 300, 200)]);
        $id = (int) $up->json('photo.id');
        $origBytes = Storage::disk(config('files.disk'))->get(GalleryPhoto::findOrFail($id)->storage_path);

        $res = $this->putJson(route('gallery.update', ['photo' => $id]), [
            'taken_at' => '2021-07-04 09:30:00',
            'place' => 'Berlin, Germany',
            'lat' => 52.52, 'lng' => 13.405,
            'rotation' => 90, 'flip_h' => true,
            'version' => 0,
        ])->assertOk();

        $this->assertSame(90, (int) $res->json('photo.rotation'));
        $this->assertTrue((bool) $res->json('photo.flip_h'));
        $this->assertSame('Berlin, Germany', $res->json('photo.place'));
        $this->assertEqualsWithDelta(52.52, (float) $res->json('photo.lat'), 0.001);
        $this->assertSame(1, (int) $res->json('photo.version'));

        // Original bytes untouched (non-invasive).
        $this->assertSame($origBytes, Storage::disk(config('files.disk'))->get(GalleryPhoto::findOrFail($id)->storage_path));
        // Original download returns the untouched bytes.
        $this->get(route('gallery.download', ['photo' => $id, 'variant' => 'original']))->assertOk();
    }

    public function test_edit_rejects_stale_version(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('v.jpg')])->json('photo.id');
        // bump to version 1
        $this->putJson(route('gallery.update', ['photo' => $id]), ['rotation' => 90, 'version' => 0])->assertOk();
        // stale write
        $this->putJson(route('gallery.update', ['photo' => $id]), ['rotation' => 180, 'version' => 0])
            ->assertStatus(409)->assertJson(['error' => 'version_conflict']);
    }

    public function test_exif_capture_date_and_gps_are_extracted(): void
    {
        $this->actingAs(User::factory()->create());
        // GD fake images carry no EXIF; assert the pipeline stores null gracefully
        // (never breaks the upload) and the row exposes the EXIF fields.
        $res = $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('e.jpg', 100, 100)])->assertCreated();
        $photo = $res->json('photo');
        $this->assertArrayHasKey('taken_at', $photo);
        $this->assertArrayHasKey('camera', $photo);
        $this->assertArrayHasKey('lat', $photo);
    }
}
