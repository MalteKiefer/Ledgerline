<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\AppSettings;
use App\Models\Photo;
use App\Services\Gallery\ExifWriter;
use App\Services\Gallery\GalleryFormats;
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

    public function test_editing_name_date_and_location_locks_the_metadata_and_regeocodes(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Las Vegas, NV, USA',
            'address' => ['city' => 'Las Vegas', 'state' => 'Nevada', 'country' => 'USA'],
        ])]);
        $this->signIn();
        $photo = Photo::factory()->create(['meta_locked' => false, 'latitude' => 1.0, 'longitude' => 2.0, 'place' => 'Old place']);

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
        // The place was re-geocoded for the new coordinates.
        $this->assertSame('Las Vegas, NV, USA', $photo->place);
    }

    public function test_edited_download_bakes_edits_and_returns_a_jpeg(): void
    {
        Storage::fake('files');
        $this->signIn();

        $img = imagecreatetruecolor(6, 4);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $photo = Photo::factory()->create([
            'disk_path' => 'photos/x.jpg', 'mime_type' => 'image/jpeg', 'name' => 'x.jpg',
            'rotation' => 90, 'taken_at' => now(), 'latitude' => 52.5, 'longitude' => 13.4, 'camera' => 'TestCam',
        ]);
        Storage::disk('files')->put('photos/x.jpg', $jpeg);

        $res = $this->get(route('gallery.download.edited', $photo));

        $res->assertOk();
        $res->assertDownload('x.jpg');
        $this->assertSame("\xFF\xD8", substr($res->getFile()->getContent(), 0, 2)); // JPEG SOI marker
    }

    public function test_edited_heic_download_stays_heic_and_is_not_relabelled_jpeg(): void
    {
        $formats = app(GalleryFormats::class);
        if (! $formats->heicSupported() || ! app(ExifWriter::class)->available()) {
            $this->markTestSkipped('Needs a HEIC-capable Imagick and exiftool.');
        }

        Storage::fake('files');
        $this->signIn();

        $imagick = new \Imagick;
        $imagick->newImage(6, 4, new \ImagickPixel('#112233'));
        $imagick->setImageFormat('heic');
        $heic = $imagick->getImagesBlob();
        $imagick->clear();

        $photo = Photo::factory()->create([
            'disk_path' => 'photos/x.heic', 'mime_type' => 'image/heic', 'name' => 'x.heic',
            'rotation' => 90, 'taken_at' => now(), 'latitude' => 52.5, 'longitude' => 13.4, 'camera' => 'TestCam',
        ]);
        Storage::disk('files')->put('photos/x.heic', $heic);

        $res = $this->get(route('gallery.download.edited', $photo));

        $res->assertOk()->assertDownload('x.heic');
        $content = $res->getFile()->getContent();
        // Not a JPEG (SOI marker) — the edited HEIC kept its container.
        $this->assertNotSame("\xFF\xD8", substr($content, 0, 2));
        // ISO-BMFF 'ftyp' box marks a HEIF/HEIC container.
        $this->assertStringContainsString('ftyp', substr($content, 0, 32));
    }

    public function test_edited_png_download_embeds_an_exif_chunk(): void
    {
        Storage::fake('files');
        $this->signIn();

        $img = imagecreatetruecolor(5, 5);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        $photo = Photo::factory()->create([
            'disk_path' => 'photos/x.png', 'mime_type' => 'image/png', 'name' => 'x.png',
            'rotation' => 0, 'taken_at' => now(), 'camera' => 'PngCam',
        ]);
        Storage::disk('files')->put('photos/x.png', $png);

        $res = $this->get(route('gallery.download.edited', $photo));

        $res->assertOk()->assertDownload('x.png');
        $content = $res->getFile()->getContent();
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($content, 0, 8)); // PNG signature
        $this->assertStringContainsString('eXIf', $content); // EXIF chunk present
    }

    public function test_edited_motion_photo_downloads_still_and_clip_as_a_zip(): void
    {
        Storage::fake('files');
        $this->signIn();

        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $photo = Photo::factory()->create([
            'disk_path' => 'photos/m.jpg', 'motion_path' => 'photos/m.mov',
            'mime_type' => 'image/jpeg', 'media_type' => 'image', 'name' => 'm.jpg',
            'taken_at' => now(), 'camera' => 'MotionCam',
        ]);
        Storage::disk('files')->put('photos/m.jpg', $jpeg);
        Storage::disk('files')->put('photos/m.mov', 'video-bytes');

        // A motion photo bundles still + clip into a zip.
        $res = $this->get(route('gallery.download.edited', $photo));
        $res->assertOk();
        $this->assertSame('application/zip', $res->headers->get('Content-Type'));
    }

    public function test_bulk_download_zips_the_selected_photos(): void
    {
        Storage::fake('files');
        $this->signIn();
        $a = Photo::factory()->create(['name' => 'a.jpg', 'disk_path' => 'photos/a.jpg']);
        $b = Photo::factory()->create(['name' => 'b.jpg', 'disk_path' => 'photos/b.jpg']);
        Storage::disk('files')->put('photos/a.jpg', 'AAA');
        Storage::disk('files')->put('photos/b.jpg', 'BBB');

        $res = $this->post(route('gallery.download'), ['photo_ids' => [$a->id, $b->id]]);

        $res->assertOk();
        $this->assertSame('application/zip', $res->headers->get('Content-Type'));
    }

    public function test_bulk_location_rejects_more_than_1000_photos(): void
    {
        $this->signIn();

        $this->post(route('gallery.location'), [
            'photo_ids' => range(1, 1001),
            'latitude' => 1.0,
            'longitude' => 2.0,
        ])->assertSessionHasErrors('photo_ids');
    }

    public function test_editing_sets_the_camera(): void
    {
        $this->signIn();
        $photo = Photo::factory()->create(['camera' => 'Old Cam']);

        $this->put(route('gallery.meta', $photo), [
            'name' => 'shot.jpg',
            'date' => '2026-05-01',
            'time' => '10:00',
            'camera' => 'Canon EOS R5',
        ])->assertRedirect();

        $this->assertSame('Canon EOS R5', $photo->fresh()->camera);
    }

    public function test_removing_coordinates_clears_the_place(): void
    {
        $this->signIn();
        $photo = Photo::factory()->create(['latitude' => 10.0, 'longitude' => 20.0, 'place' => 'Somewhere']);

        $this->put(route('gallery.meta', $photo), [
            'name' => 'No location.jpg',
            'date' => '2026-05-01',
            'time' => '09:00',
        ])->assertRedirect();

        $photo->refresh();
        $this->assertNull($photo->latitude);
        $this->assertNull($photo->place);
    }

    public function test_forward_geocode_search_returns_candidates(): void
    {
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([
            ['display_name' => 'Paris, France', 'lat' => '48.8566', 'lon' => '2.3522'],
            ['display_name' => 'Paris, Texas, USA', 'lat' => '33.6609', 'lon' => '-95.5555'],
        ])]);
        $this->signIn();

        $this->getJson(route('gallery.geocode.search', ['q' => 'Paris']))
            ->assertOk()
            ->assertJsonPath('results.0.display', 'Paris, France')
            ->assertJsonPath('results.0.lat', 48.8566);
    }

    public function test_reverse_geocode_endpoint_returns_place_and_lines(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Berlin, Germany',
            'address' => ['city' => 'Berlin', 'country' => 'Germany'],
        ])]);
        $this->signIn();

        $this->getJson(route('gallery.geocode.reverse', ['lat' => 52.52, 'lon' => 13.405]))
            ->assertOk()
            ->assertJson(['place' => 'Berlin, Germany'])
            ->assertJsonStructure(['place', 'lines']);
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
        AppSettings::current()->update(['gallery_filename_template' => '{{y}}-{{MM}}-{{dd}}']);

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
        AppSettings::current()->update(['gallery_filename_template' => '{{y}}-{{MM}}-{{dd}}']);

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
