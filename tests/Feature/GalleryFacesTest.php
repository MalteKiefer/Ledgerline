<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Services\GalleryFaceProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryFacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /** Seed a photo with one detected face grouped into a person. */
    private function seedFace(User $user, ?string $name = null): array
    {
        $photo = GalleryPhoto::query()->forceCreate([
            'user_id' => $user->id, 'storage_path' => 'gallery/'.uniqid(),
            'name' => 'p.jpg', 'mime' => 'image/jpeg', 'media_type' => 'image',
            'status' => 'ready', 'size' => 10, 'version' => 1,
        ]);
        $person = GalleryPerson::query()->forceCreate(['user_id' => $user->id, 'name' => $name]);
        $face = GalleryFace::query()->forceCreate([
            'user_id' => $user->id, 'gallery_photo_id' => $photo->id,
            'gallery_person_id' => $person->id, 'box' => [0.1, 0.1, 0.4, 0.4],
            'score' => 0.99, 'crop_path' => 'gallery/faces/'.uniqid().'.jpg',
        ]);
        $person->forceFill(['cover_face_id' => $face->id])->save();

        return [$photo, $person, $face];
    }

    public function test_face_processor_is_a_noop_when_face_ml_is_off(): void
    {
        config(['ml.face_enabled' => false]);
        $user = User::factory()->create();
        [$photo] = $this->seedFace($user);
        // Wipe the seeded face so we can assert nothing gets created.
        GalleryFace::query()->delete();

        app(GalleryFaceProcessor::class)->process($photo->refresh());

        $this->assertSame(0, GalleryFace::query()->count());
    }

    public function test_people_lists_clusters_with_visible_face_counts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, $person] = $this->seedFace($user, 'Ada');

        $res = $this->get(route('gallery.people'))->assertOk();
        $res->assertJsonPath('people.0.id', $person->id);
        $res->assertJsonPath('people.0.name', 'Ada');
        $res->assertJsonPath('people.0.count', 1);
    }

    public function test_people_returns_empty_without_faces(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('gallery.people'))->assertOk()->assertJsonCount(0, 'people');
    }

    public function test_person_photos_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $person] = $this->seedFace($owner);

        $this->actingAs($other)->get(route('gallery.people.show', ['person' => $person->id]))->assertNotFound();
        $this->actingAs($owner)->get(route('gallery.people.show', ['person' => $person->id]))->assertOk();
    }

    public function test_rename_person(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, $person] = $this->seedFace($user);

        $this->put(route('gallery.people.update', ['person' => $person->id]), ['name' => 'Grace'])->assertOk();
        $this->assertSame('Grace', $person->refresh()->name);
    }

    public function test_merge_reassigns_faces_and_removes_source(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, $from, $fromFace] = $this->seedFace($user);
        [, $into] = $this->seedFace($user, 'Target');

        $this->post(route('gallery.people.merge'), ['from_id' => $from->id, 'into_id' => $into->id])->assertOk();

        $this->assertNull(GalleryPerson::query()->find($from->id));
        $this->assertSame($into->id, $fromFace->refresh()->gallery_person_id);
    }

    public function test_face_assign_moves_face_to_a_new_named_person(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, , $face] = $this->seedFace($user);

        $this->post(route('gallery.faces.assign', ['face' => $face->id]), ['name' => 'Linus'])->assertOk();

        $person = GalleryPerson::query()->where('name', 'Linus')->firstOrFail();
        $this->assertSame($person->id, $face->refresh()->gallery_person_id);
    }

    public function test_face_hide_marks_it_hidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, , $face] = $this->seedFace($user);

        $this->post(route('gallery.faces.hide', ['face' => $face->id]))->assertOk();
        $this->assertTrue((bool) $face->refresh()->hidden);
    }

    public function test_face_crop_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, , $face] = $this->seedFace($owner);
        Storage::disk(config('files.disk'))->put($face->crop_path, 'jpgbytes');

        $this->actingAs($other)->get(route('gallery.faces.crop', ['face' => $face->id]))->assertNotFound();
        $this->actingAs($owner)->get(route('gallery.faces.crop', ['face' => $face->id]))->assertOk();
    }
}
