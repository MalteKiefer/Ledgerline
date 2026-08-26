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
use Illuminate\Support\Str;
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

    public function test_exif_endpoint_returns_sections_and_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = new GalleryPhoto;
        $photo->forceFill([
            'user_id' => $owner->id, 'storage_path' => 'gallery/x', 'name' => 'IMG.HEIC',
            'mime' => 'image/heic', 'media_type' => 'image', 'status' => 'ready', 'size' => 4200,
            'width' => 5712, 'height' => 4284, 'lat' => 48.137264, 'lng' => 11.574978, 'camera' => 'Apple iPhone Air',
            'exif' => ['EXIF' => ['FNumber' => '1.6', 'ISOSpeedRatings' => '50'], 'GPS' => ['GPSAltitude' => '516']],
        ])->save();

        $this->actingAs($owner)->getJson(route('gallery.exif', ['photo' => $photo->id]))
            ->assertOk()
            ->assertJsonPath('exif.EXIF.FNumber', '1.6')
            ->assertJsonPath('exif.GPS.GPSAltitude', '516')
            ->assertJsonPath('lat', 48.137264)
            ->assertJsonPath('camera', 'Apple iPhone Air');

        $this->actingAs($other)->getJson(route('gallery.exif', ['photo' => $photo->id]))->assertNotFound();
    }

    private function makePhoto(User $owner, array $attrs = []): GalleryPhoto
    {
        $photo = new GalleryPhoto;
        $photo->forceFill(array_merge([
            'user_id' => $owner->id, 'storage_path' => 'gallery/'.Str::uuid(), 'name' => 'p.jpg',
            'mime' => 'image/jpeg', 'media_type' => 'image', 'status' => 'ready', 'size' => 10,
        ], $attrs))->save();

        return $photo;
    }

    public function test_timeline_reads_readiness_from_db_not_disk(): void
    {
        $owner = User::factory()->create();
        // A ready photo whose rendition file does NOT exist on the (empty fake) disk:
        // if row() stat-ed the disk it would report thumb=false. It reports true →
        // it reads the DB flag, never the disk (the ~38k-stat timeout is gone).
        $this->makePhoto($owner, ['thumb_ready' => true, 'preview_ready' => true, 'taken_at' => now()->subDay()]);
        $this->makePhoto($owner, ['thumb_ready' => false, 'preview_ready' => false, 'taken_at' => now()]);

        $res = $this->actingAs($owner)->getJson(route('gallery.data'))->assertOk();
        $this->assertCount(2, $res->json('photos'));
        // Newest-first: the not-ready photo (taken now) is first.
        $this->assertFalse($res->json('photos.0.thumb'));
        $this->assertTrue($res->json('photos.1.thumb'));
        $this->assertTrue($res->json('photos.1.preview'));
    }

    public function test_timeline_paginates_with_keyset_cursor(): void
    {
        $owner = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->makePhoto($owner, ['taken_at' => now()->subDays($i), 'thumb_ready' => true, 'preview_ready' => true]);
        }

        $p1 = $this->actingAs($owner)->getJson(route('gallery.data', ['limit' => 2]))->assertOk();
        $this->assertCount(2, $p1->json('photos'));
        $this->assertNotNull($p1->json('next_cursor'));

        $seen = collect($p1->json('photos'))->pluck('id')->all();
        $cursor = $p1->json('next_cursor');
        for ($guard = 0; $guard < 10 && $cursor !== null; $guard++) {
            $page = $this->getJson(route('gallery.data', ['limit' => 2, 'cursor' => $cursor]))->assertOk();
            $seen = array_merge($seen, collect($page->json('photos'))->pluck('id')->all());
            $cursor = $page->json('next_cursor');
        }
        // No gaps or dupes across the keyset boundary; all 5 seen exactly once.
        $this->assertCount(5, array_unique($seen));
        $this->assertCount(5, $seen);
    }

    public function test_dates_histogram_counts_by_month(): void
    {
        $owner = User::factory()->create();
        $this->makePhoto($owner, ['taken_at' => '2026-08-10 12:00:00', 'thumb_ready' => true]);
        $this->makePhoto($owner, ['taken_at' => '2026-08-20 12:00:00', 'thumb_ready' => true]);
        $this->makePhoto($owner, ['taken_at' => '2026-07-01 12:00:00', 'thumb_ready' => true]);

        $res = $this->actingAs($owner)->getJson(route('gallery.dates'))->assertOk();
        $months = collect($res->json('months'));
        $this->assertSame('2026-08', $months->first()['ym']); // newest-first
        $this->assertSame(2, $months->firstWhere('ym', '2026-08')['count']);
        $this->assertSame(1, $months->firstWhere('ym', '2026-07')['count']);

        // cursor_ym jumps into that month (August rows first).
        $jump = $this->getJson(route('gallery.data', ['cursor_ym' => '2026-08']))->assertOk();
        $this->assertGreaterThanOrEqual(1, count($jump->json('photos')));
    }

    public function test_edit_resets_readiness_until_worker_rerenders(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $photo = $this->makePhoto($owner, ['thumb_ready' => true, 'preview_ready' => true]);

        $this->actingAs($owner)->putJson(route('gallery.update', ['photo' => $photo->id]), ['rotation' => 90])->assertOk();

        $fresh = $photo->fresh();
        $this->assertSame(1, (int) $fresh->version);
        $this->assertFalse((bool) $fresh->thumb_ready);
        $this->assertFalse((bool) $fresh->preview_ready);
        Queue::assertPushed(GenerateGalleryThumbnail::class);
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
        // A browser-viewable full-size preview is produced alongside the thumbnail.
        Storage::disk(config('files.disk'))->assertExists('gallery/preview/'.$id.'-'.$v.'.webp');
        $prev = $this->get(route('gallery.preview', ['photo' => $id]))->assertOk();
        $this->assertSame('image/webp', $prev->headers->get('Content-Type'));
    }

    public function test_trashed_photo_thumbnail_and_preview_still_serve(): void
    {
        // The trash view shows thumbnails; the byte endpoints must resolve a
        // soft-deleted photo (route binding is withTrashed) — otherwise 404.
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->image('t.png', 200, 200),
        ])->json('photo.id');
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk(); // renders + caches

        GalleryPhoto::findOrFail($id)->delete(); // soft-delete → trash
        $this->assertNotNull(GalleryPhoto::withTrashed()->find($id)->deleted_at);

        // Both still serve the cached bytes for the trashed photo.
        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
        $this->get(route('gallery.preview', ['photo' => $id]))->assertOk();
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

    /**
     * Pairs that landed as two separate entries — uploaded before the merge
     * existed, or in two batches — can be folded together afterwards. The clip's
     * bytes must survive: the video row goes, the file becomes the live part.
     */
    public function test_pairing_folds_an_existing_still_and_clip_into_one_entry(): void
    {
        $this->actingAs(User::factory()->create());
        $disk = Storage::disk(config('files.disk'));

        $stillId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('IMG_9.heic')])->json('photo.id');
        $clipId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('IMG_9.mov', 40, 'video/quicktime')])->json('photo.id');
        $clipPath = (string) GalleryPhoto::findOrFail($clipId)->storage_path;
        $this->assertSame(2, GalleryPhoto::count());

        $this->postJson(route('gallery.pair-live'))->assertOk()->assertJsonPath('merged', 1);

        // One entry left, and it plays the clip whose bytes are still on disk.
        $this->assertSame(1, GalleryPhoto::count());
        $still = GalleryPhoto::findOrFail($stillId);
        $this->assertSame($clipPath, $still->motion_path);
        $disk->assertExists($clipPath);
        $this->get(route('gallery.motion', ['photo' => $stillId]))->assertOk();
    }

    /**
     * A long video that happens to share a name is a video, not a live photo.
     * Hiding it inside a still would take it out of the grid for good.
     */
    public function test_pairing_leaves_a_long_video_alone(): void
    {
        $this->actingAs(User::factory()->create());
        $stillId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('CLIP.jpg')])->json('photo.id');
        $clipId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('CLIP.mov', 40, 'video/quicktime')])->json('photo.id');
        GalleryPhoto::query()->whereKey($clipId)->update(['duration' => 900]);

        $this->postJson(route('gallery.pair-live'))->assertOk()->assertJsonPath('merged', 0);

        $this->assertSame(2, GalleryPhoto::count());
        $this->assertNull(GalleryPhoto::findOrFail($stillId)->motion_path);
    }

    /** Someone else's library is never touched, and never counted. */
    public function test_pairing_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('IMG_7.heic')]);
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('IMG_7.mov', 40, 'video/quicktime')]);

        $this->actingAs(User::factory()->create());
        $this->postJson(route('gallery.pair-live'))->assertOk()->assertJsonPath('merged', 0);
        $this->assertSame(2, GalleryPhoto::withoutGlobalScopes()->count());
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

    public function test_pixel_motion_photo_uses_the_declared_length(): void
    {
        $this->actingAs(User::factory()->create());
        // Current Pixel/Samsung: the XMP container states the clip's exact length,
        // and the clip is the last thing in the file. A trailing byte after it must
        // not end up in the video.
        $clip = pack('N', 9012).'ftyp'.'isom'.str_repeat("\0", 9000);
        $xmp = '<Container:Directory><rdf:Seq><rdf:li><Container:Item Item:Mime="video/mp4" '
            .'Item:Semantic="MotionPhoto" Item:Length="'.strlen($clip).'" Item:Padding="0"/></rdf:li></rdf:Seq></Container:Directory>';
        $base = UploadedFile::fake()->image('PXL.jpg', 60, 60);
        $jpeg = (string) file_get_contents($base->getRealPath());

        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->createWithContent('PXL.jpg', $jpeg.$xmp.$clip),
        ])->assertCreated()->json('photo.id');

        $photo = GalleryPhoto::findOrFail($id);
        $this->assertNotNull($photo->motion_path);
        $this->assertSame($clip, Storage::disk(config('files.disk'))->get((string) $photo->motion_path));
    }

    public function test_samsung_marker_motion_photo_is_extracted(): void
    {
        $this->actingAs(User::factory()->create());
        $clip = pack('N', 9012).'ftyp'.'isom'.str_repeat("\0", 9000);
        $base = UploadedFile::fake()->image('SAM.jpg', 60, 60);
        $jpeg = (string) file_get_contents($base->getRealPath());

        $id = (int) $this->post(route('gallery.upload'), [
            'file' => UploadedFile::fake()->createWithContent('SAM.jpg', $jpeg.'MotionPhoto_Data'.$clip),
        ])->assertCreated()->json('photo.id');

        $this->assertNotNull(GalleryPhoto::findOrFail($id)->motion_path);
    }

    public function test_a_clip_that_arrives_alone_folds_into_its_still(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $stillId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('IMG_9.heic')])->json('photo.id');
        $clipId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('IMG_9.mov', 40, 'video/quicktime')])->json('photo.id');

        // Both halves carry the same asset identifier — the state the worker reaches
        // after reading it out of each file.
        $still = GalleryPhoto::findOrFail($stillId);
        $clip = GalleryPhoto::findOrFail($clipId);
        $asset = '11111111-2222-3333-4444-555555555555';
        $still->forceFill(['content_id' => $asset])->save();
        $clip->forceFill(['content_id' => $asset, 'media_type' => 'video'])->save();
        $clipBytes = (string) $clip->storage_path;

        app(GalleryController::class)->linkLivePhoto($still->refresh());

        $still->refresh();
        $this->assertSame($clipBytes, $still->motion_path, 'the still now owns the clip');
        $this->assertNull(GalleryPhoto::withoutGlobalScopes()->find($clipId), 'the separate tile is gone');
        Storage::disk(config('files.disk'))->assertExists($clipBytes);
    }

    public function test_folding_never_reaches_across_users(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $stillId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('IMG_10.heic')])->json('photo.id');

        $this->actingAs(User::factory()->create());
        $clipId = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('IMG_10.mov', 40, 'video/quicktime')])->json('photo.id');

        $asset = '99999999-8888-7777-6666-555555555555';
        $still = GalleryPhoto::withoutGlobalScopes()->findOrFail($stillId);
        $still->forceFill(['content_id' => $asset])->save();
        GalleryPhoto::withoutGlobalScopes()->findOrFail($clipId)->forceFill(['content_id' => $asset, 'media_type' => 'video'])->save();

        app(GalleryController::class)->linkLivePhoto($still);

        $this->assertNull($still->refresh()->motion_path);
        $this->assertNotNull(GalleryPhoto::withoutGlobalScopes()->find($clipId));
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
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-t', '1',
            '-metadata', 'creation_time=2026-06-13T19:10:51.000000Z', $mp4,
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
        // Capture date read from the container's creation_time tag.
        $this->assertNotNull($photo->taken_at);
        $this->assertSame('2026-06-13', $photo->taken_at?->format('Y-m-d'));
        Storage::disk(config('files.disk'))->assertExists((string) $photo->poster_path);
        Storage::disk(config('files.disk'))->assertExists('gallery/thumb/'.$id.'-'.$photo->version.'.webp');

        $this->get(route('gallery.thumb', ['photo' => $id]))->assertOk();
        $this->get(route('gallery.play', ['photo' => $id]))->assertOk();
    }

    public function test_semantic_search_is_empty_when_ml_disabled(): void
    {
        config()->set('ml.enabled', false);
        $this->actingAs(User::factory()->create());
        // Filename must NOT contain the query term — search also matches OCR text +
        // filename (works without ML), so a matching name would be a false hit here.
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('IMG_1234.jpg', 40, 40)]);

        // ML off (or no pgvector) and no text/name match → graceful empty result;
        // the client falls back to its own name filter.
        $this->get(route('gallery.search', ['q' => 'tree']))->assertOk()->assertExactJson(['photos' => []]);
        // Blank query short-circuits too.
        $this->get(route('gallery.search', ['q' => '']))->assertOk()->assertExactJson(['photos' => []]);
    }

    public function test_duplicates_is_empty_without_pgvector(): void
    {
        // sqlite/no-pgvector → Vector::available() false → empty groups (graceful).
        $this->actingAs(User::factory()->create());
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('d.jpg', 40, 40)]);
        $this->get(route('gallery.duplicates'))->assertOk()->assertExactJson(['groups' => []]);
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

    public function test_memories_on_this_day_and_trips(): void
    {
        $this->actingAs(User::factory()->create());
        $today = now();

        $seed = function (string $takenAt, ?string $place = null) {
            $p = new GalleryPhoto;
            $p->forceFill([
                'storage_path' => 'gallery/'.Str::uuid(),
                'name' => 'p.jpg', 'mime' => 'image/jpeg', 'media_type' => 'image', 'status' => 'ready',
                'size' => 10, 'taken_at' => $takenAt, 'place' => $place,
            ])->save();

            return $p->id;
        };

        // On this day: same month/day, two years ago.
        $onThis = $seed($today->copy()->subYears(2)->format('Y-m-d').' 12:00:00');
        // A trip: 12 photos spanning 4 days last month, same place.
        $tripStart = $today->copy()->subMonth()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $seed($tripStart->copy()->addDays(intdiv($i, 3))->format('Y-m-d H:i:s'), 'Rome');
        }

        $res = $this->getJson(route('gallery.memories'))->assertOk()->json();

        $this->assertNotEmpty($res['on_this_day']);
        $this->assertSame(2, $res['on_this_day'][0]['years_ago']);
        $this->assertSame($onThis, $res['on_this_day'][0]['photos'][0]['id']);

        $this->assertNotEmpty($res['trips']);
        $this->assertSame('Rome', $res['trips'][0]['place']);
        $this->assertSame(12, $res['trips'][0]['count']);

        // Themes are empty without ML/pgvector.
        $this->assertSame([], $res['themes']);
    }

    public function test_archived_photos_hide_from_timeline_and_show_in_archive(): void
    {
        $this->actingAs(User::factory()->create());
        $a = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('a.jpg', 120, 90)])->json('photo.id');
        $b = (int) $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('b.jpg', 200, 150)])->json('photo.id');

        $this->patch(route('gallery.archive', ['photo' => $a]), ['archived' => true])->assertOk();

        // main timeline excludes the archived one
        $ids = collect($this->get(route('gallery.data'))->json('photos'))->pluck('id')->all();
        $this->assertSame([$b], $ids);

        // archive view shows only it, flagged archived
        $arch = $this->get(route('gallery.data', ['archived' => 1]))->json('photos');
        $this->assertCount(1, $arch);
        $this->assertSame($a, $arch[0]['id']);
        $this->assertTrue($arch[0]['archived']);

        // bulk unarchive returns it to the timeline
        $this->post(route('gallery.bulk-archive'), ['ids' => [$a], 'archived' => false])->assertOk();
        $this->assertCount(2, $this->get(route('gallery.data'))->json('photos'));
    }

    public function test_data_reports_totals_for_the_whole_set_not_the_page(): void
    {
        // The header counted the rows the client had loaded, which on a
        // paginated timeline is the page size: eighteen thousand photos read as
        // "200". The totals must describe the filtered set.
        $user = User::factory()->create();
        $this->actingAs($user);

        $seed = function (int $userId, string $mediaType, ?string $archivedAt = null): void {
            (new GalleryPhoto)->forceFill([
                'user_id' => $userId,
                'storage_path' => 'gallery/'.Str::uuid(),
                'name' => 'p.jpg', 'mime' => 'image/jpeg', 'media_type' => $mediaType,
                'status' => 'ready', 'size' => 10, 'archived_at' => $archivedAt,
            ])->save();
        };

        for ($i = 0; $i < 3; $i++) {
            $seed($user->id, 'image');
        }
        $seed($user->id, 'video');
        $seed($user->id, 'image');

        $body = $this->getJson(route('gallery.data', ['limit' => 2]))->assertOk()->json();

        $this->assertCount(2, $body['photos'], 'one page');
        $this->assertSame(4, $body['totals']['images']);
        $this->assertSame(1, $body['totals']['videos']);

        // Archived photos are out of the timeline, so they are out of its counts.
        $seed($user->id, 'image', now()->toDateTimeString());
        $this->assertSame(4, $this->getJson(route('gallery.data'))->assertOk()->json('totals.images'));

        // And another account's library never shows up in them.
        $stranger = User::factory()->create();
        $seed($stranger->id, 'video');
        $this->assertSame(1, $this->getJson(route('gallery.data'))->assertOk()->json('totals.videos'));
    }

    public function test_format_duplicates_find_a_reexported_library_but_never_a_burst(): void
    {
        // A library exported once as HEIC and again as JPEG puts every picture in
        // the timeline twice. The bytes differ, so the upload's sha256 check
        // cannot see it. The signal is the capture second PLUS more than one
        // format — a burst shares the second but is always one format.
        $user = User::factory()->create();
        $this->actingAs($user);

        $seed = function (string $name, string $mime, string $takenAt) use ($user): void {
            (new GalleryPhoto)->forceFill([
                'user_id' => $user->id, 'storage_path' => 'gallery/'.Str::uuid(),
                'name' => $name, 'mime' => $mime, 'media_type' => 'image', 'status' => 'ready',
                'size' => 10, 'taken_at' => $takenAt,
            ])->save();
        };

        // The same shot, twice, in two formats.
        $seed('IMG_1.HEIC', 'image/heic', '2026-06-16 07:03:49');
        $seed('IMG_1.JPEG', 'image/jpeg', '2026-06-16 07:03:49');
        // A burst: same second, one format. Must be left alone.
        $seed('IMG_2.JPEG', 'image/jpeg', '2026-06-16 08:00:00');
        $seed('IMG_3.JPEG', 'image/jpeg', '2026-06-16 08:00:00');
        // A lone photo.
        $seed('IMG_4.HEIC', 'image/heic', '2026-06-16 09:00:00');

        $body = $this->getJson(route('gallery.duplicates.formats'))->assertOk()->json();

        $this->assertCount(1, $body['groups'], 'only the two-format group');
        $names = collect($body['groups'][0]['photos'])->pluck('name')->sort()->values()->all();
        $this->assertSame(['IMG_1.HEIC', 'IMG_1.JPEG'], $names);
        // Counts, not key order: the map is sorted most-common-first, and with a
        // tie there is no meaningful first.
        $this->assertEqualsCanonicalizing(['image/heic' => 1, 'image/jpeg' => 1], $body['formats']);
    }

    public function test_format_duplicates_never_reach_another_library(): void
    {
        $stranger = User::factory()->create();
        foreach (['image/heic', 'image/jpeg'] as $mime) {
            (new GalleryPhoto)->forceFill([
                'user_id' => $stranger->id, 'storage_path' => 'gallery/'.Str::uuid(),
                'name' => 'x', 'mime' => $mime, 'media_type' => 'image', 'status' => 'ready',
                'size' => 10, 'taken_at' => '2026-06-16 07:03:49',
            ])->save();
        }

        $this->actingAs(User::factory()->create());
        $this->assertCount(0, $this->getJson(route('gallery.duplicates.formats'))->assertOk()->json('groups'));
    }

    public function test_format_duplicates_leave_video_alone(): void
    {
        // A live photo's clip and a standalone recording can share a second
        // without being the same thing, so one video format against another is
        // not the re-export signal this scan looks for.
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['a.mov', 'video/quicktime'], ['b.mp4', 'video/mp4']] as [$name, $mime]) {
            (new GalleryPhoto)->forceFill([
                'user_id' => $user->id, 'storage_path' => 'gallery/'.Str::uuid(),
                'name' => $name, 'mime' => $mime, 'media_type' => 'video', 'status' => 'ready',
                'size' => 10, 'taken_at' => '2026-06-16 07:03:49',
            ])->save();
        }

        $this->assertCount(0, $this->getJson(route('gallery.duplicates.formats'))->assertOk()->json('groups'));
    }

    public function test_a_photo_stored_without_a_size_gets_one_when_the_job_runs_again(): void
    {
        // PHP's getimagesize does not understand HEIC, so those uploads stored no
        // size at all — and the pixel-budget guard, which reads the same call,
        // silently did not apply to them either. The size is read from the header
        // now, and the backfill re-queues the photos that missed it.
        Storage::fake((string) config('files.disk'));
        $user = User::factory()->create();
        $this->actingAs($user);

        // A real JPEG, but with the size deliberately unrecorded, standing in for
        // the HEIC rows: the point is the path that fills a missing size.
        // Held in a variable first: the fake is collected before the read
        // otherwise, which is the pitfall this suite has hit before.
        $base = UploadedFile::fake()->image('x.jpg', 120, 80);
        $bytes = file_get_contents((string) $base->getRealPath());
        $path = 'gallery/'.Str::uuid();
        Storage::disk((string) config('files.disk'))->put($path, (string) $bytes);
        $photo = new GalleryPhoto;
        $photo->forceFill([
            'user_id' => $user->id, 'storage_path' => $path, 'name' => 'x.jpg', 'mime' => 'image/jpeg',
            'media_type' => 'image', 'status' => 'ready', 'size' => strlen((string) $bytes),
            'width' => null, 'height' => null, 'thumb_ready' => true, 'preview_ready' => true,
        ])->save();

        // Both renditions present: the job takes the early branch, which is
        // exactly where the backfilled photos land.
        Storage::disk((string) config('files.disk'))->put('gallery/thumb/'.$photo->id.'-'.$photo->version.'.webp', 'x');
        Storage::disk((string) config('files.disk'))->put('gallery/preview/'.$photo->id.'-'.$photo->version.'.webp', 'x');

        app(GalleryController::class)->generateThumb($photo->fresh(), app(ImageManagerFactory::class));

        $this->assertSame(120, (int) $photo->fresh()?->width);
        $this->assertSame(80, (int) $photo->fresh()?->height);
    }
}
