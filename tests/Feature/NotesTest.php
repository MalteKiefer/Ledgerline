<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('notes.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_loads_without_a_vault(): void
    {
        $this->signIn();
        $this->get(route('notes.index'))->assertOk();
    }

    public function test_it_creates_a_note_with_tags(): void
    {
        $this->signIn();

        $this->post(route('notes.store'), [
            'title' => 'Shopping', 'content' => '- milk', 'tags' => 'home, food',
        ])->assertRedirect();

        $note = Note::firstWhere('title', 'Shopping');
        $this->assertSame(['home', 'food'], $note->tags);
    }

    public function test_json_create_for_file_migration(): void
    {
        $this->signIn();

        $this->postJson(route('notes.store'), ['title' => 'Migrated', 'content' => '# hi'])
            ->assertCreated()->assertJsonStructure(['id']);

        $this->assertSame(1, Note::where('title', 'Migrated')->count());
    }

    public function test_trash_and_empty_trash(): void
    {
        $this->signIn();
        $note = Note::create(['title' => 'Temp', 'content' => 'x']);

        $this->post(route('notes.trash', $note))->assertRedirect();
        $this->assertNotNull($note->refresh()->trashed_at);

        $this->delete(route('notes.trash.empty'))->assertRedirect();
        $this->assertSame(0, Note::count());
    }

    public function test_notes_appear_in_global_search(): void
    {
        $this->signIn();
        Note::create(['title' => 'Uniquenote', 'content' => 'searchable body text']);

        $this->getJson(route('search.suggest', ['q' => 'searchable']))
            ->assertOk()->assertSee('Uniquenote');
    }
}
