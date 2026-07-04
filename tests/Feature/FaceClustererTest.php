<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Face;
use App\Models\Person;
use App\Models\Photo;
use App\Services\Gallery\FaceClusterer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceClustererTest extends TestCase
{
    use RefreshDatabase;

    /** A clusterer whose nearest-match is driven by the test (no pgvector). */
    private function clusterer(?Face &$match): FaceClusterer
    {
        return new class($match) extends FaceClusterer
        {
            public function __construct(private ?Face &$m) {}

            protected function nearestPersonFace(Face $face): ?Face
            {
                return $this->m;
            }
        };
    }

    private function face(array $attrs = []): Face
    {
        $photo = Photo::factory()->create();

        return Face::create(array_merge([
            'photo_id' => $photo->id, 'det_score' => 0.9,
            'box_x1' => 0.1, 'box_y1' => 0.1, 'box_x2' => 0.4, 'box_y2' => 0.4,
        ], $attrs));
    }

    public function test_assign_starts_a_new_person_without_a_match(): void
    {
        $match = null;
        $f = $this->face();

        $this->clusterer($match)->assign($f);

        $f->refresh();
        $this->assertNotNull($f->person_id);
        $person = Person::find($f->person_id);
        $this->assertSame(1, $person->faces_count);
        $this->assertSame($f->id, $person->cover_face_id);
    }

    public function test_assign_adopts_the_matched_person(): void
    {
        $match = null;
        $first = $this->face(['det_score' => 0.95]);
        $this->clusterer($match)->assign($first);
        $first->refresh();

        // Second face matches the first → same person.
        $match = $first;
        $second = $this->face(['det_score' => 0.80]);
        $this->clusterer($match)->assign($second);

        $this->assertSame($first->person_id, $second->fresh()->person_id);
        $this->assertSame(2, Person::find($first->person_id)->faces_count);
        // Cover stays the higher-scoring face.
        $this->assertSame($first->id, Person::find($first->person_id)->cover_face_id);
    }

    public function test_recluster_preserves_pinned_faces(): void
    {
        $match = null;
        $clusterer = $this->clusterer($match);

        $pinnedPerson = Person::create(['name' => 'Alice']);
        $pinned = $this->face(['person_id' => $pinnedPerson->id, 'pinned' => true]);
        $loose = $this->face(['pinned' => false, 'person_id' => null]);

        $clusterer->recluster();

        // Pinned face keeps its person; loose face gets some person.
        $this->assertSame($pinnedPerson->id, $pinned->fresh()->person_id);
        $this->assertNotNull($loose->fresh()->person_id);
    }
}
