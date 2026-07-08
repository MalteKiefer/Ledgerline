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

    public function test_the_page_and_data_load(): void
    {
        $this->signIn();
        $this->get(route('notes.index'))->assertOk();
        $this->getJson(route('notes.data'))->assertOk()->assertJson(['notes' => []]);
    }

    public function test_it_creates_an_encrypted_note(): void
    {
        $this->signIn();

        $this->postJson(route('notes.store'), ['enc_note' => 'sealed-blob'])
            ->assertCreated()->assertJson(['enc_note' => 'sealed-blob']);

        $this->assertSame(1, Note::count());
    }

    public function test_patch_trashes_and_empty_trash(): void
    {
        $this->signIn();
        $note = Note::create(['enc_note' => 'sealed-blob', 'is_encrypted' => true]);

        $this->patchJson(route('notes.patch', $note), ['trashed' => true])->assertOk()->assertJson(['trashed' => true]);
        $this->deleteJson(route('notes.trash.empty'))->assertOk();
        $this->assertSame(0, Note::withTrashed()->count());
    }

    public function test_a_trashed_note_can_be_restored_and_stays_listed(): void
    {
        $this->signIn();
        $note = Note::create(['enc_note' => 'sealed-blob', 'is_encrypted' => true]);

        // Trash it: soft-deleted, but still returned by the data endpoint so the
        // client can show the trash view.
        $this->patchJson(route('notes.patch', $note), ['trashed' => true])->assertOk();
        $this->assertTrue($note->fresh()->trashed());
        $this->getJson(route('notes.data'))->assertOk()->assertJsonFragment(['id' => $note->id, 'trashed' => true]);

        // Restore it via the same toggle (route must resolve the trashed model).
        $this->patchJson(route('notes.patch', $note), ['trashed' => false])->assertOk()->assertJson(['trashed' => false]);
        $this->assertFalse($note->fresh()->trashed());
    }

    public function test_destroy_permanently_deletes_a_note(): void
    {
        $this->signIn();
        $note = Note::create(['enc_note' => 'sealed-blob', 'is_encrypted' => true]);
        $note->delete();

        $this->deleteJson(route('notes.destroy', $note))->assertOk();
        $this->assertSame(0, Note::withTrashed()->count());
    }
}
