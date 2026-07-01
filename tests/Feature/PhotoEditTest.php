<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\CompanyProfile;
use App\Models\Photo;
use App\Services\Gallery\PhotoStorage;
use App\Services\Gallery\VideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_name_date_and_location_locks_the_metadata(): void
    {
        $this->signIn();
        $photo = Photo::factory()->create(['meta_locked' => false]);

        $this->put(route('gallery.meta', $photo), [
            'name' => 'Vegas trip.jpg',
            'date' => '2026-05-01',
            'time' => '14:30',
            'latitude' => 36.1699,
            'longitude' => -115.1398,
        ])->assertRedirect();

        $photo->refresh();
        $this->assertSame('Vegas trip.jpg', $photo->name);
        $this->assertSame('2026-05-01 14:30', $photo->taken_at->format('Y-m-d H:i'));
        $this->assertSame(36.1699, $photo->latitude);
        $this->assertTrue($photo->meta_locked);
    }

    public function test_rotating_stores_the_angle_and_requeues_processing(): void
    {
        Queue::fake();
        $this->signIn();
        $photo = Photo::factory()->create(['rotation' => 0]);

        $this->post(route('gallery.transform', $photo), ['action' => 'rotate_right'])->assertRedirect();
        $this->assertSame(90, $photo->fresh()->rotation);

        $this->post(route('gallery.transform', $photo), ['action' => 'rotate_left'])->assertRedirect();
        $this->assertSame(0, $photo->fresh()->rotation);

        $this->post(route('gallery.transform', $photo), ['action' => 'flip'])->assertRedirect();
        $this->assertTrue($photo->fresh()->flipped);

        Queue::assertPushed(ProcessPhoto::class, 3);
    }

    public function test_rescan_does_not_overwrite_locked_metadata(): void
    {
        Storage::fake('files');
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Somewhere'])]);
        $this->signIn();

        // A photo with hand-edited, locked metadata.
        $photo = Photo::factory()->create([
            'meta_locked' => true,
            'taken_at' => '2020-01-01 00:00:00',
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        // Put a real image at the original path so processing can run.
        $image = UploadedFile::fake()->image('x.jpg', 100, 100);
        Storage::disk('files')->put($photo->disk_path, $image->getContent());

        app(PhotoStorage::class)->process($photo->fresh());

        $photo->refresh();
        $this->assertSame('2020-01-01 00:00', $photo->taken_at->format('Y-m-d H:i'));
        $this->assertSame(10.0, $photo->latitude);
        $this->assertSame('ready', $photo->status);
    }

    public function test_video_metadata_pulls_gps_and_reverse_geocodes(): void
    {
        Storage::fake('files');
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'San Francisco, CA'])]);
        $this->signIn();

        $this->app->instance(VideoProcessor::class, new class extends VideoProcessor
        {
            public function __construct() {}

            public function probe(string $localPath): array
            {
                return ['width' => 1920, 'height' => 1080, 'duration' => 10, 'raw' => ['format' => ['tags' => [
                    'com.apple.quicktime.location.ISO6709' => '+37.7858-122.4064+010.000/',
                    'com.apple.quicktime.make' => 'Apple',
                    'com.apple.quicktime.model' => 'iPhone 15',
                    'com.apple.quicktime.creationdate' => '2026-06-06T10:08:38+0200',
                    'creation_time' => '2026-06-06T08:08:38.000000Z',
                ]]]];
            }
        });

        $photo = Photo::factory()->create(['media_type' => 'video', 'mime_type' => 'video/mp4']);
        Storage::disk('files')->put($photo->disk_path, 'video-bytes');

        app(PhotoStorage::class)->readMetadata($photo->fresh());

        $photo->refresh();
        $this->assertSame(37.7858, $photo->latitude);
        $this->assertSame(-122.4064, $photo->longitude);
        $this->assertSame('Apple iPhone 15', $photo->camera);
        $this->assertSame('San Francisco, CA', $photo->place);
        // The local-timezone creationdate (wall-clock 10:08) wins over the UTC
        // creation_time (08:08), so the capture time reads as it was shot.
        $this->assertSame('2026-06-06 10:08:38', $photo->taken_at->format('Y-m-d H:i:s'));
    }

    public function test_processing_applies_the_filename_template_with_metadata(): void
    {
        Storage::fake('files');
        $this->signIn();
        CompanyProfile::current()->update(['gallery_filename_template' => '{{y}}-{{MM}}-{{dd}}']);

        $image = UploadedFile::fake()->image('orig.jpg', 100, 100);
        $photo = Photo::factory()->create([
            'name' => 'orig.jpg',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'taken_at' => '2024-03-04 09:00:00',
        ]);
        Storage::disk('files')->put($photo->disk_path, $image->getContent());

        app(PhotoStorage::class)->process($photo->fresh());

        // The upload pipeline renames from the template using the capture date.
        $this->assertSame('2024-03-04.jpg', $photo->fresh()->name);
    }

    public function test_template_appends_a_counter_for_a_different_image_same_second(): void
    {
        Storage::fake('files');
        $this->signIn();
        CompanyProfile::current()->update(['gallery_filename_template' => '{{y}}-{{MM}}-{{dd}}']);

        // A different image already occupies the templated name.
        Photo::factory()->create(['name' => '2024-03-04.jpg', 'checksum' => 'other-checksum']);

        $image = UploadedFile::fake()->image('b.jpg', 100, 100);
        $photo = Photo::factory()->create([
            'name' => 'b.jpg',
            'original_name' => 'b.jpg',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'checksum' => 'mine',
            'taken_at' => '2024-03-04 10:00:00',
        ]);
        Storage::disk('files')->put($photo->disk_path, $image->getContent());

        app(PhotoStorage::class)->process($photo->fresh());

        $this->assertSame('2024-03-04_2.jpg', $photo->fresh()->name);
    }

    public function test_reading_metadata_extracts_an_embedded_motion_clip(): void
    {
        Storage::fake('files');
        $this->signIn();

        $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
        $bytes = "\xFF\xD8 jpeg body \xFF\xD9".$mp4;

        $photo = Photo::factory()->create(['media_type' => 'image', 'mime_type' => 'image/jpeg']);
        Storage::disk('files')->put($photo->disk_path, $bytes);

        app(PhotoStorage::class)->readMetadata($photo->fresh());

        $photo->refresh();
        $this->assertNotNull($photo->motion_path);
        $this->assertTrue($photo->hasMotion());
        Storage::disk('files')->assertExists($photo->motion_path);
    }

    public function test_reading_metadata_reverse_geocodes_the_place(): void
    {
        Storage::fake('files');
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Bayreuth, Bavaria, Germany'])]);
        $this->signIn();

        $photo = Photo::factory()->create(['latitude' => 50.0, 'longitude' => 11.5, 'place' => null]);
        $image = UploadedFile::fake()->image('x.jpg', 100, 100);
        Storage::disk('files')->put($photo->disk_path, $image->getContent());

        app(PhotoStorage::class)->readMetadata($photo->fresh());

        $this->assertSame('Bayreuth, Bavaria, Germany', $photo->fresh()->place);
    }
}
