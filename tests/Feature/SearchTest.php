<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(string $term): TestResponse
    {
        return $this->get(route('search', ['q' => $term]));
    }

    public function test_guests_cannot_search(): void
    {
        $this->get(route('search'))->assertRedirect(route('login'));
    }

    public function test_empty_query_shows_a_prompt(): void
    {
        $this->signIn();

        $this->get(route('search'))->assertOk()->assertSee('Type a term');
    }

    public function test_it_finds_photos_by_place(): void
    {
        $this->signIn();
        Photo::factory()->create(['name' => 'Beach.jpg', 'place' => 'Qzxwvtown, Portugal']);

        $this->search('qzxwvtown')
            ->assertOk()
            ->assertSee('Photos')
            ->assertSee('Beach.jpg');
    }

    public function test_results_are_grouped_by_entity_type(): void
    {
        $this->signIn();
        Photo::factory()->create(['name' => 'Qzxwv.jpg']);

        $this->search('qzxwv')->assertOk()->assertViewHas('groups', function (array $groups): bool {
            return array_key_exists('Photos', $groups);
        });
    }

    public function test_unmatched_query_reports_no_results(): void
    {
        $this->signIn();
        Photo::factory()->create(['name' => 'Acme.jpg']);

        $this->search('zzz-no-such-token-zzz')->assertOk()->assertSee('No results');
    }

    public function test_guests_cannot_use_the_suggest_endpoint(): void
    {
        $this->get(route('search.suggest', ['q' => 'x']))->assertRedirect(route('login'));
    }

    public function test_suggest_returns_grouped_json(): void
    {
        $this->signIn();
        Photo::factory()->create(['name' => 'Qzxwv.jpg']);

        $this->getJson(route('search.suggest', ['q' => 'qzxwv']))
            ->assertOk()
            ->assertJsonPath('groups.0.group', 'Photos')
            ->assertJsonFragment(['title' => 'Qzxwv.jpg']);
    }

    public function test_suggest_for_empty_term_returns_no_groups(): void
    {
        $this->signIn();

        $this->getJson(route('search.suggest'))->assertOk()->assertExactJson(['groups' => []]);
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $this->signIn();
        Note::create(['title' => 'discount a% code', 'content' => '']);
        Note::create(['title' => 'banana', 'content' => '']);

        // "a%" must match only the note literally containing "a%", not every
        // note with an "a" (which an unescaped % wildcard would).
        $this->search('a%')
            ->assertOk()
            ->assertSee('discount a% code')
            ->assertDontSee('banana');
    }
}
