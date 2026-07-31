<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\NoteSearchController;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Server-side notes search + tag aggregation foundation. Exercises the portable
 * LIKE fallback (the suite runs on sqlite; the pgsql GIN full-text index is a
 * perf-only addition). Owner isolation is asserted.
 *
 * The real routes are wired separately; these tests register the two GET
 * endpoints against the controller so the controller can be exercised directly.
 */
class NoteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_test/notes/search', [NoteSearchController::class, 'search']);
        Route::get('/_test/notes/tags', [NoteSearchController::class, 'tags']);
    }

    public function test_search_matches_title_body_and_tag(): void
    {
        $this->actingAs(User::factory()->create());

        Note::create(['title' => 'Einkaufsliste', 'body' => 'nichts', 'tags' => ['privat']]);
        Note::create(['title' => 'Arbeit', 'body' => 'Deploy planen', 'tags' => ['work']]);
        Note::create(['title' => 'Rezept', 'body' => 'Kuchen', 'tags' => ['einkauf', 'kochen']]);

        // Title match.
        $this->getJson('/_test/notes/search?q=Einkaufsliste')
            ->assertOk()->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Einkaufsliste');

        // Body match.
        $this->getJson('/_test/notes/search?q=Deploy')
            ->assertOk()->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Arbeit');

        // Tag match (matches the "einkauf" tag only, not the "Einkaufsliste" title).
        $this->getJson('/_test/notes/search?q=kochen')
            ->assertOk()->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Rezept');
    }

    public function test_search_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other);
        Note::create(['title' => 'Geheim', 'body' => 'other user note', 'tags' => []]);

        $this->actingAs($owner);
        Note::create(['title' => 'Meins', 'body' => 'owner note', 'tags' => []]);

        // Owner sees only their own; the other user's matching note is invisible.
        $this->getJson('/_test/notes/search?q=note')
            ->assertOk()->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Meins');
        $this->getJson('/_test/notes/search?q=Geheim')
            ->assertOk()->assertJsonCount(0, 'notes');
    }

    public function test_empty_query_returns_empty(): void
    {
        $this->actingAs(User::factory()->create());
        Note::create(['title' => 'Etwas', 'body' => 'da', 'tags' => ['x']]);

        $this->getJson('/_test/notes/search?q=')
            ->assertOk()->assertExactJson(['notes' => []]);
        $this->getJson('/_test/notes/search?q=%20%20')
            ->assertOk()->assertExactJson(['notes' => []]);
    }

    public function test_tags_aggregation_counts_and_sorts(): void
    {
        $this->actingAs(User::factory()->create());

        Note::create(['title' => 'a', 'body' => '', 'tags' => ['work', 'privat']]);
        Note::create(['title' => 'b', 'body' => '', 'tags' => ['work', 'einkauf']]);
        Note::create(['title' => 'c', 'body' => '', 'tags' => ['work']]);
        Note::create(['title' => 'd', 'body' => '', 'tags' => ['privat']]);

        $response = $this->getJson('/_test/notes/tags')->assertOk();
        $response->assertJsonCount(3, 'tags');

        // work=3 (top), privat=2, then einkauf=1 (count desc, then name asc).
        $response->assertJsonPath('tags.0', ['tag' => 'work', 'count' => 3]);
        $response->assertJsonPath('tags.1', ['tag' => 'privat', 'count' => 2]);
        $response->assertJsonPath('tags.2', ['tag' => 'einkauf', 'count' => 1]);
    }

    public function test_tags_aggregation_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other);
        Note::create(['title' => 'x', 'body' => '', 'tags' => ['foreign']]);

        $this->actingAs($owner);
        Note::create(['title' => 'y', 'body' => '', 'tags' => ['mine']]);

        $this->getJson('/_test/notes/tags')
            ->assertOk()->assertJsonCount(1, 'tags')
            ->assertJsonPath('tags.0', ['tag' => 'mine', 'count' => 1]);
    }
}
