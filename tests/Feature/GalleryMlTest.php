<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Services\Gallery\MachineLearning;
use App\Support\Vector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gallery ML re-integration. The immich sidecar + pgvector are absent in the
 * sqlite test env, so most tests exercise the graceful-degrade + relational
 * plumbing (people/faces rows, owner scope, crop streaming, empty search). One
 * upload test binds a fake MachineLearning to prove the wiring: a face row +
 * crop + auto-created person. Vector-only assertions are guarded.
 */
class GalleryMlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function uploadPhoto(): int
    {
        return (int) $this->post(route('gallery.rel.upload'), [
            'file' => UploadedFile::fake()->image('p.jpg', 800, 600),
        ])->assertCreated()->json('photo.id');
    }

    private function makePerson(User $u, ?string $name = null): GalleryPerson
    {
        $p = new GalleryPerson;
        $p->forceFill(['user_id' => $u->id, 'name' => $name]);
        $p->save();

        return $p;
    }

    private function makeFace(User $u, int $photoId, ?GalleryPerson $person, bool $hidden = false, ?string $crop = null): GalleryFace
    {
        $f = new GalleryFace;
        $f->forceFill([
            'user_id' => $u->id,
            'gallery_photo_id' => $photoId,
            'gallery_person_id' => $person?->id,
            'score' => 0.9,
            'box' => [0.1, 0.1, 0.5, 0.5],
            'crop_path' => $crop,
            'hidden' => $hidden,
        ]);
        $f->save();

        return $f;
    }

    // ---- Graceful degrade (ML off / sqlite) ----

    public function test_upload_with_ml_disabled_stores_no_ml_data(): void
    {
        config()->set('gallery.ml_enabled', false);
        $this->actingAs(User::factory()->create());

        $id = $this->uploadPhoto();
        $photo = GalleryPhoto::findOrFail($id);

        $this->assertNull($photo->embedded_at);
        $this->assertSame(0, GalleryFace::query()->where('gallery_photo_id', $id)->count());
        $this->assertSame(0, GalleryPerson::query()->count());
    }

    public function test_search_returns_empty_on_sqlite(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('gallery.rel.search', ['q' => 'a cat on a sofa']))
            ->assertOk()->assertJsonCount(0, 'photos');
    }

    public function test_similar_returns_empty_on_sqlite(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->uploadPhoto();

        $this->getJson(route('gallery.rel.similar', $id))
            ->assertOk()->assertJsonCount(0, 'photos');
    }

    // ---- People CRUD (relational, owner-scoped) ----

    public function test_people_list_only_shows_people_with_visible_faces(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $photo = $this->uploadPhoto();

        $visible = $this->makePerson($u);
        $this->makeFace($u, $photo, $visible);
        $hiddenOnly = $this->makePerson($u);
        $this->makeFace($u, $photo, $hiddenOnly, hidden: true);

        $ids = collect($this->getJson(route('gallery.rel.people'))->assertOk()->json('people'))
            ->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($hiddenOnly->id, $ids);
    }

    public function test_person_rename_optimistic_conflict(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $person = $this->makePerson($u);

        $this->putJson(route('gallery.rel.people.update', $person->id), ['name' => 'Alice', 'version' => 0])
            ->assertOk()->assertJsonPath('person.name', 'Alice');
        $this->assertSame('Alice', GalleryPerson::findOrFail($person->id)->name);

        $this->putJson(route('gallery.rel.people.update', $person->id), ['name' => 'Bob', 'version' => 0])
            ->assertStatus(409)->assertJsonPath('error', 'version_conflict');
    }

    public function test_person_show_returns_faces_and_photos(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $photo = $this->uploadPhoto();
        $person = $this->makePerson($u, 'Carol');
        $this->makeFace($u, $photo, $person);

        $this->getJson(route('gallery.rel.people.show', $person->id))
            ->assertOk()
            ->assertJsonPath('person.name', 'Carol')
            ->assertJsonCount(1, 'faces')
            ->assertJsonCount(1, 'photos');
    }

    public function test_assign_and_hide_face(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $photo = $this->uploadPhoto();
        $p1 = $this->makePerson($u);
        $p2 = $this->makePerson($u);
        $face = $this->makeFace($u, $photo, $p1);

        $this->postJson(route('gallery.rel.faces.assign', $face->id), ['gallery_person_id' => $p2->id])
            ->assertOk()->assertJsonPath('face.gallery_person_id', $p2->id);

        $this->postJson(route('gallery.rel.faces.assign', $face->id), ['gallery_person_id' => null])
            ->assertOk()->assertJsonPath('face.gallery_person_id', null);

        $this->postJson(route('gallery.rel.faces.hide', $face->id))
            ->assertOk()->assertJsonPath('face.hidden', true);
        $this->assertTrue(GalleryFace::findOrFail($face->id)->hidden);
    }

    public function test_merge_people_reassigns_faces_and_soft_deletes_source(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $photo = $this->uploadPhoto();
        $source = $this->makePerson($u);
        $target = $this->makePerson($u);
        $face = $this->makeFace($u, $photo, $source);

        $this->postJson(route('gallery.rel.people.merge'), ['source_id' => $source->id, 'target_id' => $target->id])
            ->assertOk()->assertJsonPath('target_id', $target->id);

        $this->assertSame($target->id, GalleryFace::findOrFail($face->id)->gallery_person_id);
        $this->assertSoftDeleted('gallery_people', ['id' => $source->id]);
    }

    public function test_delete_person_detaches_faces(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $photo = $this->uploadPhoto();
        $person = $this->makePerson($u);
        $face = $this->makeFace($u, $photo, $person);

        $this->deleteJson(route('gallery.rel.people.destroy', $person->id))->assertOk();

        $this->assertSoftDeleted('gallery_people', ['id' => $person->id]);
        $this->assertNull(GalleryFace::findOrFail($face->id)->gallery_person_id);
    }

    public function test_people_are_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a);
        $person = $this->makePerson($a);

        $this->actingAs($b)->getJson(route('gallery.rel.people.show', $person->id))->assertNotFound();
    }

    // ---- Face crop streaming ----

    public function test_face_crop_stream_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a);
        $photo = $this->uploadPhoto();
        $person = $this->makePerson($a);

        $cropPath = 'gallery/faces/'.Str::uuid()->toString().'.jpg';
        Storage::disk(config('files.disk'))->put($cropPath, 'jpeg-bytes');
        $face = $this->makeFace($a, $photo, $person, crop: $cropPath);

        $this->get(route('gallery.rel.faces.crop', $face->id))->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        // No crop → 404.
        $noCrop = $this->makeFace($a, $photo, $person);
        $this->get(route('gallery.rel.faces.crop', $noCrop->id))->assertNotFound();

        // Foreign user → 404 (owner global scope on the binding).
        $this->actingAs($b)->get(route('gallery.rel.faces.crop', $face->id))->assertNotFound();
    }

    // ---- Mocked-ML upload: face + person + crop wiring ----

    public function test_mocked_ml_upload_creates_face_person_and_crop(): void
    {
        $this->app->instance(MachineLearning::class, $this->fakeMl());
        $this->actingAs(User::factory()->create());

        $id = $this->uploadPhoto();
        $photo = GalleryPhoto::findOrFail($id);

        // ML ran → embedded_at stamped even without pgvector.
        $this->assertNotNull($photo->embedded_at);

        // One face row, assigned to an auto-created person.
        $faces = GalleryFace::query()->where('gallery_photo_id', $id)->get();
        $this->assertCount(1, $faces);
        $this->assertNotNull($faces[0]->gallery_person_id);
        $this->assertSame(1, GalleryPerson::query()->count());

        // Crop written to disk (only when imagick can render the crop).
        if (extension_loaded('imagick')) {
            $this->assertNotNull($faces[0]->crop_path);
            Storage::disk(config('files.disk'))->assertExists((string) $faces[0]->crop_path);
        }

        // Second similar photo: grouping is a pgvector feature — guarded.
        $id2 = $this->uploadPhoto();
        $this->assertSame(2, GalleryFace::query()->count());
        if (Vector::available()) {
            $this->assertSame(1, GalleryPerson::query()->count(), 'similar faces group into one person');
            $shared = GalleryFace::query()->pluck('gallery_person_id')->unique();
            $this->assertCount(1, $shared);
        } else {
            $this->assertSame(2, GalleryPerson::query()->count(), 'no pgvector → each face its own person');
        }

        unset($id2);
    }

    /** A MachineLearning stand-in: enabled, fixed 512-d vector, one detected face. */
    private function fakeMl(): MachineLearning
    {
        return new class extends MachineLearning
        {
            public function enabled(): bool
            {
                return true;
            }

            public function faceEnabled(): bool
            {
                return true;
            }

            public function embed(string $path): ?array
            {
                return array_fill(0, 512, 0.1);
            }

            public function embedText(string $text): ?array
            {
                return array_fill(0, 512, 0.1);
            }

            public function detectFaces(string $path): array
            {
                return [[
                    'score' => 0.99,
                    'box' => [0.1, 0.1, 0.6, 0.6],
                    'embedding' => array_fill(0, 512, 0.1),
                ]];
            }
        };
    }
}
