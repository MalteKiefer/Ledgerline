<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Note;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_are_private_to_their_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $this->postJson(route('notes.store'), ['title' => 'Alice secret', 'content' => 'x'])->assertSuccessful();
        $this->getJson(route('notes.data'))->assertOk()->assertJsonFragment(['title' => 'Alice secret']);

        // Bob sees none of Alice's notes, and the row carries Alice's user_id.
        $this->actingAs($bob);
        $this->getJson(route('notes.data'))->assertOk()->assertJsonMissing(['title' => 'Alice secret']);
        $this->assertSame(0, Note::count()); // scoped to Bob
        $this->assertSame($alice->id, Note::withoutGlobalScopes()->first()->user_id);
    }

    public function test_todos_and_lists_are_private(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $this->postJson(route('todos.store'), ['title' => 'Alice task', 'priority' => 'normal'])->assertOk();

        $this->actingAs($bob);
        $this->assertSame(0, Todo::count());
        $this->getJson(route('todos.data'))->assertOk()->assertJsonMissing(['title' => 'Alice task']);
    }

    public function test_bookmarks_are_private(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $this->postJson(route('bookmarks.store'), ['title' => 'Alice link', 'url' => 'https://example.com'])->assertSuccessful();

        $this->actingAs($bob);
        $this->assertSame(0, Bookmark::count());
    }

    public function test_owner_is_set_automatically_on_create(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $note = Note::create(['title' => 'T', 'content' => 'c']);
        $this->assertSame($alice->id, $note->user_id);
    }
}
