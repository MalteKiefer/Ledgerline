<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plaintext-relational Notes (pivot Phase 1): per-row CRUD, optimistic version,
 * soft-delete trash + restore, owner isolation, and the migration import.
 */
class NotesRelationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_list_and_search(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('notes.rel.store'), ['title' => 'Einkauf', 'body' => 'Milch, Brot', 'tags' => ['privat']])
            ->assertCreated()->assertJsonPath('note.title', 'Einkauf');
        $this->postJson(route('notes.rel.store'), ['title' => 'Arbeit', 'body' => 'Deploy planen'])->assertCreated();

        $this->getJson(route('notes.list'))->assertOk()->assertJsonCount(2, 'notes');
        $this->getJson(route('notes.list', ['q' => 'milch']))->assertOk()->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Einkauf');
    }

    public function test_update_bumps_version_and_rejects_stale(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('notes.rel.store'), ['title' => 'A'])->json('note.id');

        $this->putJson(route('notes.rel.update', $id), ['title' => 'B', 'version' => 0])
            ->assertOk()->assertJsonPath('note.title', 'B')->assertJsonPath('note.version', 1);

        // Stale version → 409, no silent overwrite.
        $this->putJson(route('notes.rel.update', $id), ['title' => 'C', 'version' => 0])->assertStatus(409);
        $this->assertSame('B', Note::find($id)->title);
    }

    public function test_trash_restore_and_force(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('notes.rel.store'), ['title' => 'X'])->json('note.id');

        $this->deleteJson(route('notes.rel.destroy', $id))->assertOk();
        $this->getJson(route('notes.list'))->assertJsonCount(0, 'notes');
        $this->getJson(route('notes.trash'))->assertJsonCount(1, 'notes');

        $this->postJson(route('notes.rel.restore', $id))->assertOk();
        $this->getJson(route('notes.list'))->assertJsonCount(1, 'notes');

        $this->deleteJson(route('notes.rel.destroy', $id))->assertOk();
        $this->deleteJson(route('notes.rel.force', $id))->assertOk();
        $this->assertNull(Note::withTrashed()->find($id));
    }

    public function test_notes_are_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->postJson(route('notes.rel.store'), ['title' => 'secret'])->assertCreated();

        $this->actingAs($b)->getJson(route('notes.list'))->assertOk()->assertJsonCount(0, 'notes');
    }

    public function test_cannot_touch_another_users_note(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $id = $this->actingAs($a)->postJson(route('notes.rel.store'), ['title' => 'a'])->json('note.id');

        // b's request is owner-scoped → the row is invisible → 404.
        $this->actingAs($b)->putJson(route('notes.rel.update', $id), ['title' => 'hijack'])->assertNotFound();
        $this->actingAs($b)->deleteJson(route('notes.rel.destroy', $id))->assertNotFound();
    }
}
