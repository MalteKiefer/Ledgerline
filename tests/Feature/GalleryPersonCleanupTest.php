<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Face;
use App\Models\Person;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryPersonCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function personWithFace(User $user): array
    {
        $photo = Photo::factory()->create();
        $person = Person::create(['user_id' => $user->id]);
        $face = Face::create(['user_id' => $user->id, 'photo_id' => $photo->id, 'person_id' => $person->id, 'det_score' => 0.9]);
        $person->forceFill(['faces_count' => 1, 'cover_face_id' => $face->id])->save();

        return [$photo, $person];
    }

    public function test_force_deleting_the_last_photo_removes_the_person(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$photo, $person] = $this->personWithFace($user);

        $photo->forceDelete();

        $this->assertNull(Person::withoutGlobalScopes()->find($person->id));
        $this->assertSame(0, Face::where('photo_id', $photo->id)->count());
    }

    public function test_soft_deleting_a_photo_keeps_the_person(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$photo, $person] = $this->personWithFace($user);

        $photo->delete(); // trash, recoverable

        $this->assertNotNull(Person::withoutGlobalScopes()->find($person->id));
    }
}
