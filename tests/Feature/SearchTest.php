<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactFunction;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(string $term)
    {
        return $this->actingAs(User::factory()->create())
            ->get(route('search', ['q' => $term]));
    }

    public function test_guests_cannot_search(): void
    {
        $this->get(route('search'))->assertRedirect(route('login'));
    }

    public function test_empty_query_shows_a_prompt(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('search'))
            ->assertOk()
            ->assertSee('Type a term');
    }

    public function test_it_finds_customers_by_name(): void
    {
        Customer::factory()->create(['name' => 'Qzxwv Industries']);

        $this->search('qzxwv')
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee('Qzxwv Industries');
    }

    public function test_it_finds_contacts_by_related_email(): void
    {
        $contact = Contact::factory()->create([
            'name' => 'Jane Searchable',
            'function' => ContactFunction::CEO->value,
        ]);
        ContactEmail::factory()->for($contact)->create(['email' => 'find-me@qzxwv.test']);

        $this->search('qzxwv.test')
            ->assertOk()
            ->assertSee('Contacts')
            ->assertSee('Jane Searchable');
    }

    public function test_it_finds_branches_by_name(): void
    {
        Branch::factory()->create(['name' => 'Qzxwv Branch']);

        $this->search('qzxwv branch')
            ->assertOk()
            ->assertSee('Branches')
            ->assertSee('Qzxwv Branch');
    }

    public function test_it_finds_projects_by_reference(): void
    {
        Project::factory()->create(['name' => 'Some Project', 'reference' => 'QZXWV-9001']);

        $this->search('qzxwv-9001')
            ->assertOk()
            ->assertSee('Projects')
            ->assertSee('Some Project');
    }

    public function test_results_are_grouped_by_entity_type(): void
    {
        Customer::factory()->create(['name' => 'Qzxwv Customer']);
        Branch::factory()->create(['name' => 'Qzxwv Branch']);

        $response = $this->search('qzxwv');

        $response->assertOk();
        $response->assertViewHas('groups', function (array $groups): bool {
            return array_key_exists('Customers', $groups)
                && array_key_exists('Branches', $groups);
        });
    }

    public function test_unmatched_query_reports_no_results(): void
    {
        Customer::factory()->create(['name' => 'Acme']);

        $this->search('zzz-no-such-token-zzz')
            ->assertOk()
            ->assertSee('No results');
    }

    public function test_guests_cannot_use_the_suggest_endpoint(): void
    {
        $this->get(route('search.suggest', ['q' => 'x']))->assertRedirect(route('login'));
    }

    public function test_suggest_returns_grouped_json(): void
    {
        Customer::factory()->create(['name' => 'Qzxwv Industries']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('search.suggest', ['q' => 'qzxwv']))
            ->assertOk()
            ->assertJsonPath('groups.0.group', 'Customers')
            ->assertJsonFragment(['title' => 'Qzxwv Industries']);
    }

    public function test_suggest_for_empty_term_returns_no_groups(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('search.suggest'))
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    public function test_suggest_matches_contacts_via_related_email(): void
    {
        $contact = Contact::factory()->create([
            'name' => 'Jane Suggest',
            'function' => ContactFunction::CTO->value,
        ]);
        ContactEmail::factory()->for($contact)->create(['email' => 'reachme@qzxwv.test']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('search.suggest', ['q' => 'qzxwv.test']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Jane Suggest']);
    }
}
