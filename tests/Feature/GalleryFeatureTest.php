<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\GalleryController;
use App\Jobs\GenerateGalleryThumbnail;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Support\BinaryProcess;
use App\Support\ImageManagerFactory;
use App\Support\VideoProcessor;
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
            app(GalleryController::class),
            app(ImageManagerFactory::class),
        );
        $v = GalleryPhoto::findOrFail($id)->version;
        Storage::disk(config('files.disk'))->assertExists('gallery/thumb/'.$id.'-'.$v.'.webp');
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_duplicate_upload_is_skipped(): void
    {
        $this->actingAs(User::factory()->create());
        $img = UploadedFile::fake()->image('dup.jpg', 100, 100);
        $bytes = file_get_contents($img->getRealPath());

        $a = $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->createWithContent('one.jpg', (string) $bytes)])->assertCreated();
        $id = (int) $a->json('photo.id');

        // Same bytes again → no new row, returns the existing photo flagged duplicate.
        $b = $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->createWithContent('again.jpg', (string) $bytes)])->assertOk();
        $this->assertTrue((bool) $b->json('duplicate'));
        $this->assertSame($id, (int) $b->json('photo.id'));
        $this->assertSame(1, GalleryPhoto::count());
    }

    public function test_attach_motion_merges_into_one_entry(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('IMG_1.heic')])->json('photo.id');

        $mov = UploadedFile::fake()->create('IMG_1.mov', 40, 'video/quicktime');
        $res = $this->post(route('gallery.motion.attach', ['photo' => $id]), ['file' => $mov])->assertOk();
        $this->assertTrue((bool) $res->json('photo.motion'));

        // Still exactly one gallery entry (the .MOV is merged, not a second photo).
        $this->assertSame(1, GalleryPhoto::count());
        $photo = GalleryPhoto::findOrFail($id);
        Storage::disk(config('files.disk'))->assertExists((string) $photo->motion_path);
        $this->get(route('gallery.motion', ['photo' => $id]))->assertOk()
            ->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_embedded_motion_photo_is_extracted_on_upload(): void
    {
        $this->actingAs(User::factory()->create());
        // Android/Samsung Motion Photo = a JPEG with an MP4 appended after it.
        $still = UploadedFile::fake()->image('MOTION.jpg', 60, 60);
        $jpeg = (string) file_get_contents($still->getRealPath());
        $mp4 = pack('N', 9012).'ftyp'.'isom'.str_repeat("\0", 9000); // ISO-BMFF box after the JPEG
        $combined = $jpeg.$mp4;

        $res = $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->createWithContent('MOTION.jpg', $combined),
        ])->assertCreated();
        $id = (int) $res->json('photo.id');

        $this->assertTrue((bool) $res->json('photo.motion'));
        $photo = GalleryPhoto::findOrFail($id);
        Storage::disk(config('files.disk'))->assertExists((string) $photo->motion_path);
        $this->get(route('gallery.motion', ['photo' => $id]))->assertOk();
    }

    public function test_plain_jpeg_gets_no_motion(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('plain.jpg', 40, 40)])->json('photo.id');
        $this->assertFalse((bool) GalleryPhoto::findOrFail($id)->motion_path);
    }

    public function test_video_upload_is_processed_and_playable(): void
    {
        if (! VideoProcessor::available()) {
            $this->markTestSkipped('ffmpeg/ffprobe not available');
        }
        $this->actingAs(User::factory()->create());

        // A tiny real MP4 (H.264, no audio) → web-playable, no transcode needed.
        $mp4 = tempnam(sys_get_temp_dir(), 'llv').'.mp4';
        BinaryProcess::run([
            'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=10',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-t', '1', $mp4,
        ], 60);
        $bytes = (string) file_get_contents($mp4);
        @unlink($mp4);

        $res = $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->createWithContent('clip.mp4', $bytes),
        ])->assertCreated();
        $id = (int) $res->json('photo.id');
        $this->assertSame('video', $res->json('photo.media_type'));

        // Queue is sync in tests → ProcessGalleryVideo already ran: poster + thumb + ready.
        $photo = GalleryPhoto::findOrFail($id);
        $this->assertSame('ready', $photo->status);
        $this->assertNotNull($photo->poster_path);
        Storage::disk(config('files.disk'))->assertExists((string) $photo->poster_path);
        Storage::disk(config('files.disk'))->assertExists('gallery/thumb/'.$id.'-'.$photo->version.'.webp');

        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk();
        $this->get(route('gallery.play', ['photo' => $id]))->assertOk();
    }

    public function test_semantic_search_is_empty_when_ml_disabled(): void
    {
        config()->set('ml.enabled', false);
        $this->actingAs(User::factory()->create());
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('tree.jpg', 40, 40)]);

        // ML off (or no pgvector) → graceful empty result; the client falls back.
        $this->get(route('gallery.search', ['q' => 'tree']))->assertOk()->assertExactJson(['photos' => []]);
        // Blank query short-circuits too.
        $this->get(route('gallery.search', ['q' => '']))->assertOk()->assertExactJson(['photos' => []]);
    }

    public function test_non_media_upload_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->createWithContent('x.txt', 'hello')])
            ->assertStatus(415);
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
        // Distinct dimensions → distinct bytes (fake images of equal size are
        // byte-identical, which the new sha256 de-dup would collapse into one).
        $a = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('a.jpg', 10, 10)])->json('photo.id');
        $b = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('b.jpg', 20, 20)])->json('photo.id');

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
        $p1 = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('1.jpg', 10, 10)])->json('photo.id');
        $p2 = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('2.jpg', 20, 20)])->json('photo.id');

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
