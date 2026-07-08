<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Note;
use App\Models\Person;
use App\Models\Photo;
use App\Models\StoredFile;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_are_private_to_their_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $this->postJson(route('notes.store'), ['enc_note' => 'alice-secret-blob'])->assertSuccessful();
        $this->getJson(route('notes.data'))->assertOk()->assertJsonFragment(['enc_note' => 'alice-secret-blob']);

        // Bob sees none of Alice's notes, and the row carries Alice's user_id.
        $this->actingAs($bob);
        $this->getJson(route('notes.data'))->assertOk()->assertJsonMissing(['enc_note' => 'alice-secret-blob']);
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
        $this->postJson(route('bookmarks.store'), ['enc_bookmark' => 'alice-sealed'])->assertSuccessful();

        $this->actingAs($bob);
        $this->assertSame(0, Bookmark::count());
    }

    public function test_files_are_private_and_raw_download_is_owner_only(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$blob, 'secret bytes');
        StoredFile::create([
            'id' => (string) Str::uuid(), 'enc_metadata' => '{"c":"c2VhbGVkYQ==","n":"bm9uY2Vh"}', 'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}',
            'is_encrypted' => true, 'size' => 12, 'blob' => $blob, 'tags' => [],
        ]);

        // Owner can list + download.
        $this->getJson(route('files.data'))->assertOk()->assertJsonFragment(['enc_metadata' => '{"c":"c2VhbGVkYQ==","n":"bm9uY2Vh"}']);
        $this->get(route('files.raw', ['blob' => $blob]))->assertOk();

        // Bob sees no files and cannot fetch Alice's blob by its UUID.
        $this->actingAs($bob);
        $this->getJson(route('files.data'))->assertOk()->assertJsonMissing(['enc_metadata' => '{"c":"c2VhbGVkYQ==","n":"bm9uY2Vh"}']);
        $this->get(route('files.raw', ['blob' => $blob]))->assertNotFound();
    }

    public function test_gallery_photos_and_people_are_private(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        Photo::factory()->create(['uploaded_by' => $alice->id]);
        Person::create(['name' => 'Alice friend']);
        $this->assertSame(1, Photo::count());
        $this->assertSame(1, Person::count());

        // Bob's gallery + people are empty; the photo carries Alice's ownership.
        $this->actingAs($bob);
        $this->assertSame(0, Photo::count());
        $this->assertSame(0, Person::count());
        $this->assertSame($alice->id, Photo::withoutGlobalScopes()->first()->uploaded_by);
    }

    public function test_owner_is_set_automatically_on_create(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $note = Note::create(['enc_note' => 'sealed-blob', 'is_encrypted' => true]);
        $this->assertSame($alice->id, $note->user_id);
    }
}
