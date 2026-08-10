<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
