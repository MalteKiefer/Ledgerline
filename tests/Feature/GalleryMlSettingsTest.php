<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryMlSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_ml_and_paths(): void
    {
        $this->actingAs(User::factory()->create());
        $this->put(route('settings.gallery.update'), [
            'gallery_trip_gap_days' => 2, 'gallery_trip_radius_km' => 100, 'gallery_map_zoom' => 13,
            'gallery_max_upload_mb' => 200, 'gallery_video_frame' => 1, 'gallery_geocode_grid_km' => 0.5,
            'gallery_ml_enabled' => '1', 'gallery_face_enabled' => '0',
            'gallery_ffmpeg_path' => '/usr/bin/ffmpeg', 'gallery_face_min_score' => 0.8,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = AppSettings::current();
        $this->assertTrue($s->gallery_ml_enabled);
        $this->assertFalse($s->gallery_face_enabled);
        $this->assertSame('/usr/bin/ffmpeg', $s->gallery_ffmpeg_path);
        $this->assertEqualsWithDelta(0.8, $s->gallery_face_min_score, 0.001);
    }

    public function test_page_renders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('settings.gallery.edit'))->assertOk()->assertSee(__('settings.ml_heading'));
    }
}
