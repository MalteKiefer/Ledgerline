<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_gallery(): void
    {
        $this->get(route('gallery.index'))->assertRedirect(route('login'));
    }

    public function test_uploading_a_photo_stores_original_thumb_and_medium(): void
    {
        Storage::fake('files');
        $this->signIn();

        $response = $this->post(route('gallery.store'), [
            'photo' => UploadedFile::fake()->image('trip.jpg', 1200, 800),
        ]);

        $response->assertCreated();
        $photo = Photo::firstOrFail();

        $this->assertSame('trip.jpg', $photo->name);
        $this->assertSame(1200, $photo->width);
        Storage::disk('files')->assertExists($photo->disk_path);
        Storage::disk('files')->assertExists($photo->thumb_path);
        Storage::disk('files')->assertExists($photo->medium_path);
        // Stored under a date-structured prefix, independent of the files module.
        $this->assertStringStartsWith('photos/', $photo->disk_path);
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

        $this->post(route('gallery.restore', $a->id))->assertRedirect();
        $this->assertDatabaseHas('photos', ['id' => $a->id, 'deleted_at' => null]);

        $this->delete(route('gallery.force-destroy', $b->id))->assertRedirect();
        $this->assertDatabaseMissing('photos', ['id' => $b->id]);
        Storage::disk('files')->assertMissing($b->disk_path);
        Storage::disk('files')->assertMissing($b->thumb_path);
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
