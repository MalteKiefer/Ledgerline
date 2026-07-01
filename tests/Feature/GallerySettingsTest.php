<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GeneratePhotoRenditions;
use App\Jobs\ReadPhotoMetadata;
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
        ])->assertRedirect(route('settings.gallery.edit'));

        $company = CompanyProfile::current();
        $this->assertSame(5, $company->gallery_trip_gap_days);
        $this->assertSame(250, $company->gallery_trip_radius_km);
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
}
