<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_it_finds_files_by_note(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create([
            'name' => 'Contract.pdf',
            'note' => 'renewal due qzxwvnote',
        ]);

        $this->search('qzxwvnote')
            ->assertOk()
            ->assertSee('Files')
            ->assertSee('Contract.pdf');
    }

    public function test_results_are_grouped_by_entity_type(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create(['name' => 'Qzxwv.pdf']);

        $this->search('qzxwv')->assertOk()->assertViewHas('groups', function (array $groups): bool {
            return array_key_exists('Files', $groups);
        });
    }

    public function test_unmatched_query_reports_no_results(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create(['name' => 'Acme.pdf']);

        $this->search('zzz-no-such-token-zzz')->assertOk()->assertSee('No results');
    }

    public function test_guests_cannot_use_the_suggest_endpoint(): void
    {
        $this->get(route('search.suggest', ['q' => 'x']))->assertRedirect(route('login'));
    }

    public function test_suggest_returns_grouped_json(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create(['name' => 'Qzxwv.pdf']);

        $this->getJson(route('search.suggest', ['q' => 'qzxwv']))
            ->assertOk()
            ->assertJsonPath('groups.0.group', 'Files')
            ->assertJsonFragment(['title' => 'Qzxwv.pdf']);
    }

    public function test_suggest_for_empty_term_returns_no_groups(): void
    {
        $this->signIn();

        $this->getJson(route('search.suggest'))->assertOk()->assertExactJson(['groups' => []]);
    }
}
