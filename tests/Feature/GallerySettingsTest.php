<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GeneratePhotoRenditions;
use App\Jobs\ReadPhotoMetadata;
use App\Jobs\RenamePhotos;
use App\Models\CompanyProfile;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GallerySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_gallery_settings(): void
    {
        $this->get(route('settings.gallery.edit'))->assertRedirect(route('login'));
    }

    public function test_gallery_settings_page_renders_separately_from_company(): void
    {
        $this->signIn();
        $this->get(route('settings.gallery.edit'))->assertOk();
        // The company profile page no longer carries the gallery fields.
        $this->get(route('settings.company.edit'))->assertOk()->assertDontSee('gallery_trip_gap_days');
    }

    public function test_trip_thresholds_can_be_saved(): void
    {
        $this->signIn();

        $this->put(route('settings.gallery.update'), [
            'gallery_trip_gap_days' => 5,
            'gallery_trip_radius_km' => 250,
            'gallery_map_zoom' => 15,
            'gallery_filename_template' => '{{y}}-{{MM}}-{{dd}}',
            'gallery_max_upload_mb' => 500,
            'gallery_video_frame' => 3,
            'gallery_ffmpeg_path' => '/var/www/bin/ffmpeg/ffmpeg',
        ])->assertRedirect(route('settings.gallery.edit'));

        $company = CompanyProfile::current();
        $this->assertSame(5, $company->gallery_trip_gap_days);
        $this->assertSame(250, $company->gallery_trip_radius_km);
        $this->assertSame(15, $company->gallery_map_zoom);
        $this->assertSame('{{y}}-{{MM}}-{{dd}}', $company->gallery_filename_template);
        $this->assertSame(500, $company->gallery_max_upload_mb);
        $this->assertSame(3, $company->gallery_video_frame);
        $this->assertSame('/var/www/bin/ffmpeg/ffmpeg', $company->gallery_ffmpeg_path);
    }

    public function test_rescan_queues_a_metadata_job_per_photo(): void
    {
        Queue::fake();
        $this->signIn();
        Photo::factory()->count(3)->create();

        $this->post(route('settings.gallery.rescan'))->assertRedirect();

        Queue::assertPushed(ReadPhotoMetadata::class, 3);
    }

    public function test_regenerate_queues_a_rendition_job_per_photo(): void
    {
        Queue::fake();
        $this->signIn();
        Photo::factory()->count(3)->create();

        $this->post(route('settings.gallery.regenerate'))->assertRedirect();

        Queue::assertPushed(GeneratePhotoRenditions::class, 3);
    }

    public function test_rename_queues_a_rename_job_per_photo(): void
    {
        Queue::fake();
        $this->signIn();
        Photo::factory()->count(3)->create();

        $this->post(route('settings.gallery.rename'))->assertRedirect();

        Queue::assertPushed(RenamePhotos::class, 3);
    }

    public function test_queue_status_reports_pending_and_failed_counts(): void
    {
        $this->signIn();

        $this->getJson(route('settings.gallery.queue-status'))
            ->assertOk()
            ->assertJsonStructure(['connection', 'driver', 'pending', 'failed']);
    }

    public function test_a_job_can_be_limited_to_the_newest_items(): void
    {
        Queue::fake();
        $this->signIn();
        Photo::factory()->count(5)->create();

        $this->post(route('settings.gallery.rescan'), ['limit' => 2])->assertRedirect();

        Queue::assertPushed(ReadPhotoMetadata::class, 2);
    }

    public function test_run_all_queues_every_job_per_photo(): void
    {
        Queue::fake();
        $this->signIn();
        Photo::factory()->count(2)->create();

        $this->post(route('settings.gallery.run-all'))->assertRedirect();

        Queue::assertPushed(GeneratePhotoRenditions::class, 2);
        Queue::assertPushed(ReadPhotoMetadata::class, 2);
        Queue::assertPushed(RenamePhotos::class, 2);
    }
}
