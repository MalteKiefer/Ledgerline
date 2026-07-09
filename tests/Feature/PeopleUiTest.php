<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Face;
use App\Models\Person;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleUiTest extends TestCase
{
    use RefreshDatabase;

    private function personWithFaces(int $count, array $attrs = []): Person
    {
        $person = Person::create($attrs);
        for ($i = 0; $i < $count; $i++) {
            $photo = Photo::factory()->create();
            $face = Face::create(['photo_id' => $photo->id, 'person_id' => $person->id, 'det_score' => 0.9]);
            if ($i === 0) {
                $person->forceFill(['cover_face_id' => $face->id])->save();
            }
        }
        $person->forceFill(['faces_count' => $count])->save();

        return $person;
    }

    public function test_index_lists_only_people_meeting_the_minimum(): void
    {
        config(['gallery.face_min_per_person' => 2]);
        $this->signIn();
        $shown = $this->personWithFaces(3, ['name' => 'Alice']);
        $this->personWithFaces(1); // below min → hidden from grid

        $this->getJson(route('gallery.people.data'))
            ->assertOk()
            ->assertJsonCount(1, 'people')
            ->assertJsonPath('people.0.id', $shown->id)
            ->assertJsonPath('people.0.count', 3);
    }

    public function test_show_lists_the_persons_photos(): void
    {
        $this->signIn();
        $person = $this->personWithFaces(2);

        $this->getJson(route('gallery.people.show.data', ['person' => $person]))
            ->assertOk()
            ->assertJsonCount(2, 'photos');
    }

    public function test_rename_sets_the_name(): void
    {
        $this->signIn();
        $person = $this->personWithFaces(2);

        $this->patchJson(route('gallery.people.update', ['person' => $person]), ['name' => 'Bob'])
            ->assertOk();

        $this->assertSame('Bob', $person->fresh()->name);
    }

    public function test_hide_sets_hidden_at(): void
    {
        $this->signIn();
        $person = $this->personWithFaces(2);

        $this->patchJson(route('gallery.people.update', ['person' => $person]), ['hidden' => true])->assertOk();

        $this->assertNotNull($person->fresh()->hidden_at);
    }

    public function test_merge_folds_faces_and_deletes_the_source(): void
    {
        $this->signIn();
        $a = $this->personWithFaces(2, ['name' => 'Keep']);
        $b = $this->personWithFaces(1, ['name' => 'Gone']);

        $this->postJson(route('gallery.people.merge', ['person' => $a]), ['source_id' => $b->id])->assertOk();

        $this->assertDatabaseMissing('people', ['id' => $b->id]);
        $this->assertSame(3, $a->fresh()->faces_count);
        $this->assertSame(3, Face::where('person_id', $a->id)->count());
    }

    public function test_reassign_moves_and_pins_a_face(): void
    {
        $this->signIn();
        $a = $this->personWithFaces(2);
        $face = Face::where('person_id', $a->id)->first();

        $this->postJson(route('gallery.faces.reassign', ['face' => $face]), ['new' => true])->assertOk();

        $moved = $face->fresh();
        $this->assertNotSame($a->id, $moved->person_id);
        $this->assertTrue($moved->pinned);
    }

    public function test_thumb_404_when_face_has_no_crop(): void
    {
        $this->signIn();
        $person = $this->personWithFaces(1);
        $face = Face::where('person_id', $person->id)->first();

        $this->get(route('gallery.faces.thumb', ['face' => $face]))->assertNotFound();
    }
}
