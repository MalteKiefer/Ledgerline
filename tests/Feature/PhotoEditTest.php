<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Services\Gallery\PhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_date_and_location_locks_the_metadata(): void
    {
        $this->signIn();
        $photo = Photo::factory()->create(['meta_locked' => false]);

        $this->put(route('gallery.meta', $photo), [
            'date' => '2026-05-01',
            'time' => '14:30',
            'latitude' => 36.1699,
            'longitude' => -115.1398,
        ])->assertRedirect();

        $photo->refresh();
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
