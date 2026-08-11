<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DetectGalleryFaces;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Services\GalleryFaceProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    private function contactFor(User $user, string $fn): Contact
    {
        $book = AddressBook::query()->forceCreate(['user_id' => $user->id, 'name' => 'Default', 'uri' => Str::uuid()->toString()]);

        return Contact::query()->forceCreate([
            'address_book_id' => $book->id, 'uri' => Str::uuid()->toString().'.vcf',
            'etag' => Str::random(8), 'uid' => Str::uuid()->toString(), 'vcard' => "BEGIN:VCARD\nFN:{$fn}\nEND:VCARD",
            'fn' => $fn,
        ]);
    }

    public function test_set_cover_face(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$photo, $person] = $this->seedFace($user);
        $other = GalleryFace::query()->forceCreate([
            'user_id' => $user->id, 'gallery_photo_id' => $photo->id, 'gallery_person_id' => $person->id,
            'box' => [0.5, 0.5, 0.8, 0.8], 'score' => 0.9,
        ]);

        $this->put(route('gallery.people.update', ['person' => $person->id]), ['cover_face_id' => $other->id])->assertOk();
        $this->assertSame($other->id, $person->refresh()->cover_face_id);

        // A face of a different person is rejected as cover.
        [, , $foreign] = $this->seedFace($user);
        $this->put(route('gallery.people.update', ['person' => $person->id]), ['cover_face_id' => $foreign->id])->assertOk();
        $this->assertNull($person->refresh()->cover_face_id);
    }

    public function test_link_person_to_contact_sets_name_and_lists_contact_photos(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, $person] = $this->seedFace($user);
        $contact = $this->contactFor($user, 'Ada Lovelace');

        $this->put(route('gallery.people.update', ['person' => $person->id]), ['contact_id' => $contact->id])->assertOk();
        $person->refresh();
        $this->assertSame($contact->id, $person->contact_id);
        $this->assertSame('Ada Lovelace', $person->name);

        $res = $this->get(route('gallery.contact.photos', ['contact' => $contact->id]))->assertOk();
        $this->assertCount(1, $res->json('photos'));
    }

    public function test_contact_photos_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $contact = $this->contactFor($owner, 'Grace');
        $this->actingAs($other)->get(route('gallery.contact.photos', ['contact' => $contact->id]))->assertNotFound();
    }

    public function test_reprocess_queues_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->seedFace($user);
        $this->actingAs($user)->post(route('gallery.reprocess'), ['scope' => 'faces'])->assertOk()->assertJsonPath('scope', 'faces');
        Queue::assertPushed(DetectGalleryFaces::class);
    }

    public function test_person_photos_sort_direction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [, $person] = $this->seedFace($user);
        $this->get(route('gallery.people.show', ['person' => $person->id, 'sort' => 'asc']))->assertOk();
        $this->get(route('gallery.people.show', ['person' => $person->id, 'sort' => 'desc']))->assertOk();
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
