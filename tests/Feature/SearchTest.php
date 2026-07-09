<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_search(): void
    {
        $this->get(route('search'))->assertRedirect(route('login'));
    }

    public function test_empty_query_shows_a_prompt(): void
    {
        $this->signIn();

        $this->get(route('search'))->assertOk()->assertSee('Type a term');
    }

    public function test_unmatched_query_reports_no_results(): void
    {
        $this->signIn();

        // Every module is zero-knowledge, so nothing is server-searchable: any
        // term returns an empty result set rather than an error.
        $this->get(route('search', ['q' => 'zzz-no-such-token-zzz']))
            ->assertOk()
            ->assertSee('No results');
    }

    public function test_guests_cannot_use_the_suggest_endpoint(): void
    {
        $this->get(route('search.suggest', ['q' => 'x']))->assertRedirect(route('login'));
    }

    public function test_suggest_for_empty_term_returns_no_groups(): void
    {
        $this->signIn();

        $this->getJson(route('search.suggest'))->assertOk()->assertExactJson(['groups' => []]);
    }

    public function test_suggest_with_no_providers_returns_no_groups(): void
    {
        $this->signIn();

        $this->getJson(route('search.suggest', ['q' => 'anything']))
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }
}
