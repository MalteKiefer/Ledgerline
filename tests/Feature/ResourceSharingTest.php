<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\ResourceShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSharingTest extends TestCase
{
    use RefreshDatabase;

    private function noteOf(User $u, string $title): Note
    {
        $this->actingAs($u);
        $note = Note::create(['title' => $title, 'content' => 'c']);
        $this->app['auth']->forgetGuards();

        return $note;
    }

    public function test_owner_can_share_a_note_read_only_and_sharee_sees_but_cannot_edit(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Shared plan');

        // Alice shares read-only with Bob.
        $this->actingAs($alice)
            ->postJson(route('shares.store'), ['type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read'])
            ->assertCreated();

        // Bob now sees the note...
        $this->actingAs($bob);
        $this->assertSame(1, Note::count());
        $this->getJson(route('notes.data'))->assertOk()->assertJsonFragment(['title' => 'Shared plan']);

        // ...but a read-only sharee cannot edit it (central write guard → 403).
        $this->putJson(route('notes.update', $note->id), ['title' => 'Hacked', 'content' => 'x'])->assertForbidden();
        $this->assertSame('Shared plan', Note::withoutGlobalScopes()->find($note->id)->title);
    }

    public function test_write_share_allows_the_sharee_to_edit(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Doc');

        $this->actingAs($alice)
            ->postJson(route('shares.store'), ['type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'write'])
            ->assertCreated();

        $this->actingAs($bob)
            ->putJson(route('notes.update', $note->id), ['title' => 'Edited by Bob', 'content' => 'x'])
            ->assertOk();
        $this->assertSame('Edited by Bob', Note::withoutGlobalScopes()->find($note->id)->title);
    }

    public function test_a_third_user_still_cannot_see_the_note(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $note = $this->noteOf($alice, 'Private');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();

        $this->actingAs($carol);
        $this->assertSame(0, Note::count());
    }

    public function test_only_the_owner_can_share_a_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Mine');

        // Bob (not the owner) cannot share Alice's note.
        $this->actingAs($bob)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $alice->email, 'permission' => 'read',
        ])->assertForbidden();
    }

    public function test_owner_can_revoke_a_share(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Temp');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();
        $share = ResourceShare::firstOrFail();

        $this->actingAs($alice)->deleteJson(route('shares.destroy', $share->id))->assertOk();
        $this->actingAs($bob);
        $this->assertSame(0, Note::count());
    }
}
