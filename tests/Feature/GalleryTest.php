<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Services\Gallery\VideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_gallery(): void
    {
        $this->get(route('gallery.index'))->assertRedirect(route('login'));
    }

    public function test_upload_stores_the_original_and_queues_processing(): void
    {
        Storage::fake('files');
        Queue::fake();
        $this->signIn();

        $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->image('trip.jpg', 1200, 800),
        ])->assertCreated();

        $photo = Photo::firstOrFail();
        $this->assertSame('trip.jpg', $photo->name);
        $this->assertSame('processing', $photo->status);
        Storage::disk('files')->assertExists($photo->disk_path);
        // Stored under a date-structured prefix, independent of the files module.
        $this->assertStringStartsWith('photos/', $photo->disk_path);

        Queue::assertPushed(ProcessPhoto::class);
    }

    public function test_processing_job_generates_renditions_and_marks_ready(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->image('trip.jpg', 1200, 800),
        ]);
        $photo = Photo::firstOrFail();

        // The queued job runs synchronously in tests (sync driver).
        $photo->refresh();
        $this->assertSame('ready', $photo->status);
        $this->assertSame(1200, $photo->width);
        Storage::disk('files')->assertExists($photo->thumb_path);
        Storage::disk('files')->assertExists($photo->medium_path);
        $this->assertNotNull($photo->processed_at);
    }

    public function test_upload_skips_a_duplicate_file(): void
    {
        Storage::fake('files');
        Queue::fake();
        $this->signIn();

        $file = UploadedFile::fake()->image('dup.jpg', 200, 200);
        $checksum = hash_file('sha256', $file->getRealPath());

        // A photo with the same name, size and checksum already exists.
        Photo::factory()->create([
            'name' => 'dup.jpg',
            'size' => $file->getSize(),
            'checksum' => $checksum,
        ]);

        $this->post(route('gallery.store'), ['photo' => $file])
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        // No second row was created and no bytes were written.
        $this->assertSame(1, Photo::count());
        Queue::assertNotPushed(ProcessPhoto::class);
    }

    public function test_upload_accepts_a_video_and_flags_it(): void
    {
        Storage::fake('files');
        Queue::fake();
        $this->signIn();

        $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
        ])->assertCreated();

        $photo = Photo::firstOrFail();
        $this->assertSame('video', $photo->media_type);
        $this->assertTrue($photo->isVideo());
        Queue::assertPushed(ProcessPhoto::class);
    }

    public function test_video_processing_extracts_a_poster_and_metadata(): void
    {
        Storage::fake('files');
        $this->signIn();

        // Fake ffmpeg: write a real JPEG poster and report fixed probe data.
        $this->app->instance(VideoProcessor::class, new class extends VideoProcessor
        {
            public function __construct() {}

            public function poster(string $localPath, int $second, string $destJpg): void
            {
                $img = imagecreatetruecolor(320, 240);
                imagejpeg($img, $destJpg);
                imagedestroy($img);
            }

            public function probe(string $localPath): array
            {
                return ['width' => 1920, 'height' => 1080, 'duration' => 42, 'raw' => ['format' => ['duration' => '42.0']]];
            }
        });

        $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
        ]);
        $photo = Photo::firstOrFail();

        $photo->refresh();
        $this->assertSame('ready', $photo->status);
        $this->assertSame(1920, $photo->width);
        $this->assertSame(1080, $photo->height);
        $this->assertSame(42, $photo->duration);
        Storage::disk('files')->assertExists($photo->thumb_path);
        Storage::disk('files')->assertExists($photo->medium_path);
    }

    public function test_video_streams_inline_for_playback(): void
    {
        Storage::fake('files');
        $this->signIn();
        $photo = Photo::factory()->create(['media_type' => 'video', 'mime_type' => 'video/mp4']);
        Storage::disk('files')->put($photo->disk_path, 'video-bytes');

        // Either a direct file response (local disk) or a redirect to a signed
        // URL (a disk that can serve byte ranges itself) is acceptable.
        $response = $this->get(route('gallery.video', $photo));
        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_video_stream_rejects_non_video_photos(): void
    {
        Storage::fake('files');
        $this->signIn();
        $photo = Photo::factory()->create(['media_type' => 'image']);

        $this->get(route('gallery.video', $photo))->assertNotFound();
    }

    public function test_motion_clip_streams_and_requires_a_clip(): void
    {
        Storage::fake('files');
        $this->signIn();

        $with = Photo::factory()->create(['motion_path' => 'photos/2026/07/motion/x.mp4']);
        Storage::disk('files')->put($with->motion_path, 'clip-bytes');
        $this->assertContains($this->get(route('gallery.motion', $with))->status(), [200, 302]);

        $without = Photo::factory()->create(['motion_path' => null]);
        $this->get(route('gallery.motion', $without))->assertNotFound();
    }

    public function test_upload_skips_a_duplicate_even_when_it_was_renamed(): void
    {
        Storage::fake('files');
        Queue::fake();
        $this->signIn();

        $file = UploadedFile::fake()->image('holiday.jpg', 200, 200);
        $checksum = hash_file('sha256', $file->getRealPath());

        // Same bytes already stored under a template-renamed display name.
        Photo::factory()->create([
            'name' => '2024-01-01.jpg',
            'size' => $file->getSize(),
            'checksum' => $checksum,
        ]);

        $this->post(route('gallery.store'), ['photo' => $file])
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $this->assertSame(1, Photo::count());
    }

    public function test_upload_skips_heic_files(): void
    {
        Storage::fake('files');
        Queue::fake();
        $this->signIn();

        $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->create('live.heic', 500, 'image/heic'),
        ])->assertOk()->assertJson(['skipped' => true, 'reason' => 'heic']);

        $this->assertSame(0, Photo::count());
        Queue::assertNotPushed(ProcessPhoto::class);
    }

    public function test_media_counts_break_down_by_type(): void
    {
        Photo::factory()->count(2)->create(['media_type' => 'image']);
        Photo::factory()->create(['media_type' => 'video']);
        Photo::factory()->create(['media_type' => 'image', 'motion_path' => 'photos/x/motion/y.mp4']);

        $this->assertSame(
            ['total' => 4, 'images' => 3, 'videos' => 1, 'motion' => 1],
            Photo::counts(),
        );
    }

    public function test_months_endpoint_buckets_by_year_month(): void
    {
        $this->signIn();
        Photo::factory()->create(['taken_at' => '2026-06-10 10:00:00']);
        Photo::factory()->create(['taken_at' => '2026-06-20 10:00:00']);
        Photo::factory()->create(['taken_at' => '2026-05-01 10:00:00']);

        $this->getJson(route('gallery.months'))
            ->assertOk()
            ->assertJsonCount(2, 'months')
            ->assertJsonPath('months.0.ym', '2026-06')
            ->assertJsonPath('months.0.year', '2026')
            ->assertJsonPath('months.0.count', 2);
    }

    public function test_dashboard_shows_gallery_counts(): void
    {
        $this->signIn();
        Photo::factory()->create(['media_type' => 'video']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('pages.dashboard.gallery_videos'));
    }

    public function test_upload_rejects_non_images(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_timeline_groups_photos_by_capture_day(): void
    {
        $this->signIn();
        Photo::factory()->create(['name' => 'DayOne.jpg', 'taken_at' => '2026-06-01 10:00:00']);
        Photo::factory()->create(['name' => 'DayTwo.jpg', 'taken_at' => '2026-06-03 09:00:00']);

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertViewHas('grouped', fn ($grouped): bool => $grouped->has('2026-06-01') && $grouped->has('2026-06-03'));
    }

    public function test_photos_can_be_trashed_restored_and_force_deleted(): void
    {
        Storage::fake('files');
        $this->signIn();
        $a = Photo::factory()->create();
        $b = Photo::factory()->create();
        foreach ([$a, $b] as $p) {
            Storage::disk('files')->put($p->disk_path, 'x');
            Storage::disk('files')->put($p->thumb_path, 'x');
            Storage::disk('files')->put($p->medium_path, 'x');
        }

        $this->delete(route('gallery.destroy'), ['photo_ids' => [$a->id, $b->id]])->assertRedirect();
        $this->assertSoftDeleted('photos', ['id' => $a->id]);
        $this->assertSame(0, Photo::count());

        $this->post(route('gallery.restore'), ['photo_ids' => [$a->id]])->assertRedirect();
        $this->assertDatabaseHas('photos', ['id' => $a->id, 'deleted_at' => null]);

        $this->delete(route('gallery.force-destroy'), ['photo_ids' => [$b->id]])->assertRedirect();
        $this->assertDatabaseMissing('photos', ['id' => $b->id]);
        Storage::disk('files')->assertMissing($b->disk_path);
        Storage::disk('files')->assertMissing($b->thumb_path);
    }

    public function test_trash_can_be_restored_and_emptied_in_bulk(): void
    {
        Storage::fake('files');
        $this->signIn();
        $photos = Photo::factory()->count(3)->create();
        foreach ($photos as $p) {
            Storage::disk('files')->put($p->disk_path, 'x');
            $p->delete();
        }

        // Restore all.
        $this->post(route('gallery.restore'), ['all' => 1])->assertRedirect();
        $this->assertSame(3, Photo::count());

        // Trash again, then empty the whole trash.
        Photo::query()->get()->each->delete();
        $this->delete(route('gallery.force-destroy'), ['all' => 1])->assertRedirect();
        $this->assertSame(0, Photo::withTrashed()->count());
    }

    public function test_a_photo_can_be_favorited_and_unfavorited(): void
    {
        $this->signIn();
        $photo = Photo::factory()->create();

        $this->post(route('gallery.favorite', $photo))->assertRedirect();
        $this->assertNotNull($photo->fresh()->favorited_at);

        $this->post(route('gallery.favorite', $photo))->assertRedirect();
        $this->assertNull($photo->fresh()->favorited_at);
    }

    public function test_index_can_filter_to_favorites_only(): void
    {
        $this->signIn();
        $fav = Photo::factory()->create(['status' => 'ready', 'name' => 'Loved.jpg', 'favorited_at' => now()]);
        Photo::factory()->create(['status' => 'ready', 'name' => 'Plain.jpg']);

        $this->get(route('gallery.index', ['favorites' => 1]))
            ->assertOk()
            ->assertSee('Loved.jpg')
            ->assertDontSee('Plain.jpg');
    }

    public function test_timeline_renders_photo_tiles_with_metadata(): void
    {
        $this->signIn();
        Photo::factory()->create(['status' => 'ready', 'name' => 'Sunset.jpg', 'taken_at' => '2026-06-01 18:30:00', 'width' => 4000, 'height' => 3000]);

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('data-photo', false)
            ->assertSee('Sunset.jpg');
    }

    public function test_feed_returns_the_next_page_fragment(): void
    {
        $this->signIn();
        Photo::factory()->count(3)->create(['status' => 'ready']);

        $this->get(route('gallery.feed', ['page' => 1]))
            ->assertOk()
            ->assertSee('data-day', false);
    }

    public function test_map_points_returns_only_ready_geotagged_photos(): void
    {
        $this->signIn();
        Photo::factory()->create(['status' => 'ready', 'latitude' => 36.1699, 'longitude' => -115.1398]);
        Photo::factory()->create(['status' => 'ready', 'latitude' => null, 'longitude' => null]);
        Photo::factory()->create(['status' => 'processing', 'latitude' => 1, 'longitude' => 2]);

        $this->getJson(route('gallery.points'))
            ->assertOk()
            ->assertJsonCount(1, 'points')
            ->assertJsonPath('points.0.lat', 36.1699);
    }

    public function test_map_page_renders(): void
    {
        $this->signIn();
        $this->get(route('gallery.map'))->assertOk()->assertSee('photoMap(', false);
    }

    public function test_image_route_streams_a_rendition(): void
    {
        Storage::fake('files');
        $this->signIn();
        $photo = Photo::factory()->create();
        Storage::disk('files')->put($photo->thumb_path, 'bytes');

        $this->get(route('gallery.image', ['photo' => $photo, 'size' => 'thumb']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
