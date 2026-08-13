<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Models\Note;
use App\Models\NoteFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notes module (plaintext-relational): folder + note CRUD, owner isolation,
 * optimistic version → 409, recycle-bin restore/force (incl. a folder subtree),
 * and content search (sqlite LIKE path in tests).
 */
class NotesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_crud_and_data_listing(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('notes.store'), ['title' => 'Hello', 'body' => '# Hi', 'tags' => ['a', 'b']])
            ->assertCreated()->json('note.id');

        $this->getJson(route('notes.data'))
            ->assertOk()
            ->assertJsonPath('notes.0.title', 'Hello')
            ->assertJsonPath('notes.0.tags', ['a', 'b']);

        $this->getJson(route('notes.show', $id))->assertOk()->assertJsonPath('note.body', '# Hi');

        $this->putJson(route('notes.update', $id), ['title' => 'Hello 2', 'body' => 'x', 'version' => 0])
            ->assertOk()->assertJsonPath('note.title', 'Hello 2');

        $this->deleteJson(route('notes.destroy', $id))->assertOk();
        $this->assertSame(0, Note::query()->count()); // soft-deleted → out of the owner scope
        $this->assertSame(1, Note::withTrashed()->count());
    }

    public function test_attach_from_files_and_gallery_copies_bytes_and_rejects_non_media(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $this->actingAs($user);
        $id = $this->postJson(route('notes.store'), ['title' => 'N', 'body' => 'b'])->json('note.id');

        // An owner-scoped image in the Files module + a Gallery photo, both on the files disk.
        Storage::disk('files')->put('files/a.png', 'PNGBYTES');
        $file = FileEntry::forceCreate([
            'user_id' => $user->id, 'name' => 'a.png', 'storage_path' => 'files/a.png',
            'mime' => 'image/png', 'size' => 8, 'sha256' => str_repeat('0', 64), 'version' => 0,
        ]);
        Storage::disk('files')->put('gallery/g.jpg', 'JPGBYTES');
        $photo = GalleryPhoto::forceCreate([
            'user_id' => $user->id, 'name' => 'g.jpg', 'storage_path' => 'gallery/g.jpg',
            'mime' => 'image/jpeg', 'size' => 8, 'sha256' => str_repeat('1', 64), 'version' => 0,
        ]);

        $this->postJson(route('notes.attachments.from', $id), ['source' => 'file', 'id' => $file->id])
            ->assertCreated()->assertJsonPath('attachment.mime', 'image/png');
        $this->postJson(route('notes.attachments.from', $id), ['source' => 'gallery', 'id' => $photo->id])
            ->assertCreated()->assertJsonPath('attachment.mime', 'image/jpeg');
        $this->getJson(route('notes.show', $id))->assertOk()->assertJsonCount(2, 'note.attachments');

        // A non-media file cannot be embedded.
        Storage::disk('files')->put('files/d.pdf', '%PDF');
        $pdf = FileEntry::forceCreate([
            'user_id' => $user->id, 'name' => 'd.pdf', 'storage_path' => 'files/d.pdf',
            'mime' => 'application/pdf', 'size' => 4, 'sha256' => str_repeat('2', 64), 'version' => 0,
        ]);
        $this->postJson(route('notes.attachments.from', $id), ['source' => 'file', 'id' => $pdf->id])
            ->assertStatus(422);

        // Another user's file is invisible (owner scope → 404).
        $other = FileEntry::forceCreate([
            'user_id' => User::factory()->create()->id, 'name' => 'x.png', 'storage_path' => 'files/x.png',
            'mime' => 'image/png', 'size' => 1, 'sha256' => str_repeat('3', 64), 'version' => 0,
        ]);
        $this->postJson(route('notes.attachments.from', $id), ['source' => 'file', 'id' => $other->id])
            ->assertNotFound();
    }

    public function test_video_can_be_attached(): void
    {
        Storage::fake('files');
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('notes.store'), ['title' => 'V', 'body' => 'b'])->json('note.id');
        $this->post(route('notes.attachments.store', $id), [
            'file' => UploadedFile::fake()->create('clip.mp4', 20, 'video/mp4'),
        ])->assertCreated()->assertJsonPath('attachment.mime', 'video/mp4');
    }

    public function test_update_is_optimistic(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('notes.store'), ['title' => 'A', 'body' => 'a'])->json('note.id');
        // Stale version → 409 with the current version.
        $this->putJson(route('notes.update', $id), ['title' => 'B', 'body' => 'b', 'version' => 5])
            ->assertStatus(409)->assertJsonPath('error', 'version_conflict')->assertJsonPath('version', 0);
    }

    public function test_notes_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $id = $this->actingAs($owner)->postJson(route('notes.store'), ['title' => 'Secret', 'body' => 's'])->json('note.id');

        $this->actingAs(User::factory()->create());
        $this->getJson(route('notes.show', $id))->assertNotFound();
        $this->getJson(route('notes.data'))->assertOk()->assertJsonCount(0, 'notes');
    }

    public function test_trash_restore_and_force(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('notes.store'), ['title' => 'Bin', 'body' => 'b'])->json('note.id');
        $this->deleteJson(route('notes.destroy', $id))->assertOk();

        $this->getJson(route('notes.trash'))->assertOk()->assertJsonPath('notes.0.id', $id);
        $this->postJson(route('notes.restore', $id))->assertOk();
        $this->assertSame(1, Note::query()->count());

        $this->deleteJson(route('notes.destroy', $id))->assertOk();
        $this->deleteJson(route('notes.force', $id))->assertOk();
        $this->assertSame(0, Note::withTrashed()->count());
    }

    public function test_folder_delete_and_restore_covers_subtree(): void
    {
        $this->actingAs(User::factory()->create());
        $parent = $this->postJson(route('notes.folders.store'), ['name' => 'Parent'])->assertCreated()->json('folder.id');
        $child = $this->postJson(route('notes.folders.store'), ['name' => 'Child', 'parent_id' => $parent])->json('folder.id');
        $noteId = $this->postJson(route('notes.store'), ['title' => 'In child', 'body' => 'x', 'note_folder_id' => $child])->json('note.id');

        $this->deleteJson(route('notes.folders.destroy', $parent))->assertOk();
        $this->assertSame(0, NoteFolder::query()->count());
        $this->assertSame(0, Note::query()->count());

        $this->postJson(route('notes.folders.restore', $parent))->assertOk();
        $this->assertSame(2, NoteFolder::query()->count());
        $this->assertSame($noteId, Note::query()->firstOrFail()->id);
    }

    public function test_folder_cycle_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->postJson(route('notes.folders.store'), ['name' => 'A'])->json('folder.id');
        $b = $this->postJson(route('notes.folders.store'), ['name' => 'B', 'parent_id' => $a])->json('folder.id');
        // Making A a child of its own child B is a cycle → 422.
        $this->putJson(route('notes.folders.update', $a), ['name' => 'A', 'parent_id' => $b, 'version' => 0])
            ->assertStatus(422);
    }

    public function test_wikilinks_produce_backlinks_and_resolve_late(): void
    {
        $this->actingAs(User::factory()->create());
        // B exists; A links to it → A shows up in B's backlinks.
        $b = $this->postJson(route('notes.store'), ['title' => 'Target', 'body' => 'the target'])->json('note.id');
        $a = $this->postJson(route('notes.store'), ['title' => 'Source', 'body' => 'see [[Target]] and [[Ghost|alias]]'])
            ->assertCreated()->json('note.id');

        $this->getJson(route('notes.backlinks', $b))
            ->assertOk()->assertJsonCount(1, 'backlinks')->assertJsonPath('backlinks.0.id', $a);

        // The dangling [[Ghost]] link resolves once a note titled "Ghost" is created.
        $g = $this->postJson(route('notes.store'), ['title' => 'Ghost', 'body' => 'boo'])->json('note.id');
        $this->getJson(route('notes.backlinks', $g))
            ->assertOk()->assertJsonCount(1, 'backlinks')->assertJsonPath('backlinks.0.id', $a);

        // Editing A to drop the [[Target]] link removes the backlink from B.
        $this->putJson(route('notes.update', $a), ['title' => 'Source', 'body' => 'no links now', 'version' => 0])->assertOk();
        $this->getJson(route('notes.backlinks', $b))->assertOk()->assertJsonCount(0, 'backlinks');
    }

    public function test_attachment_upload_stream_delete_and_export(): void
    {
        Storage::fake('files');
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('notes.store'), ['title' => 'Doc', 'body' => 'body', 'tags' => ['x']])->json('note.id');

        $attId = $this->post(route('notes.attachments.store', $id), ['file' => UploadedFile::fake()->image('pic.png')])
            ->assertCreated()->json('attachment.id');

        // Listed on show, streamed with the sandbox CSP (defense-in-depth over
        // the mimes: allowlist that blocks svg/html on upload).
        $this->getJson(route('notes.show', $id))->assertOk()->assertJsonCount(1, 'note.attachments');
        $this->get(route('notes.attachments.raw', [$id, $attId]))
            ->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        // Markdown export carries frontmatter + body.
        $export = $this->get(route('notes.export', $id))->assertOk();
        $this->assertStringContainsString('title: "Doc"', $export->streamedContent());
        $this->assertStringContainsString('body', $export->streamedContent());

        $this->deleteJson(route('notes.attachments.destroy', [$id, $attId]))->assertOk();
        $this->getJson(route('notes.show', $id))->assertOk()->assertJsonCount(0, 'note.attachments');
    }

    public function test_search_matches_body(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('notes.store'), ['title' => 'Recipe', 'body' => 'needs saffron and rice'])->assertCreated();
        $this->postJson(route('notes.store'), ['title' => 'Other', 'body' => 'nothing here'])->assertCreated();

        $this->getJson(route('notes.search', ['q' => 'saffron']))
            ->assertOk()->assertJsonCount(1, 'notes')->assertJsonPath('notes.0.title', 'Recipe');
        $this->getJson(route('notes.search', ['q' => '']))->assertOk()->assertJsonCount(0, 'notes');
    }
}
