<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactFunction;
use App\Enums\ProjectType;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Team;
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

    public function test_it_finds_customers_by_name(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Qzxwv Industries']);

        $this->search('qzxwv')
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee('Qzxwv Industries');
    }

    public function test_it_finds_customers_by_postal_code(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Lohmar GmbH', 'postal_code' => '53797']);

        $this->search('53797')
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee('Lohmar GmbH');
    }

    public function test_it_finds_files_by_note(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        File::factory()->forCustomer($customer)->create([
            'name' => 'Contract.pdf',
            'note' => 'renewal due qzxwvnote',
        ]);

        $this->search('qzxwvnote')
            ->assertOk()
            ->assertSee('Files')
            ->assertSee('Contract.pdf');
    }

    public function test_it_finds_contacts_by_related_email(): void
    {
        $this->signIn();
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
        $this->signIn();
        Branch::factory()->create(['name' => 'Qzxwv Branch']);

        $this->search('qzxwv branch')
            ->assertOk()
            ->assertSee('Branches')
            ->assertSee('Qzxwv Branch');
    }

    public function test_it_finds_projects_by_reference(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Some Project', 'reference' => 'QZXWV-9001']);

        $this->search('qzxwv-9001')
            ->assertOk()
            ->assertSee('Projects')
            ->assertSee('Some Project');
    }

    public function test_it_finds_projects_by_tag(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Tagged Project']);
        $project->tags()->attach(Tag::findOrCreateByName('Qzxwvtag')->id);

        $this->search('qzxwvtag')->assertOk()->assertSee('Tagged Project');
    }

    public function test_it_finds_projects_by_type(): void
    {
        $this->signIn();
        Project::factory()->create([
            'name' => 'Server Patching Qzxwv',
            'type' => ProjectType::MAINTENANCE->value,
        ]);

        $this->search('maintenance')->assertOk()->assertSee('Server Patching Qzxwv');
    }

    public function test_search_does_not_leak_other_teams_records(): void
    {
        $this->signIn();
        $foreignTeam = Team::factory()->create();
        Customer::factory()->create(['team_id' => $foreignTeam->id, 'name' => 'Qzxwv Foreign Customer']);

        $this->search('qzxwv')
            ->assertOk()
            ->assertDontSee('Qzxwv Foreign Customer');
    }

    public function test_results_are_grouped_by_entity_type(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Qzxwv Customer']);
        Branch::factory()->create(['name' => 'Qzxwv Branch']);

        $this->search('qzxwv')->assertOk()->assertViewHas('groups', function (array $groups): bool {
            return array_key_exists('Customers', $groups) && array_key_exists('Branches', $groups);
        });
    }

    public function test_unmatched_query_reports_no_results(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Acme']);

        $this->search('zzz-no-such-token-zzz')->assertOk()->assertSee('No results');
    }

    public function test_guests_cannot_use_the_suggest_endpoint(): void
    {
        $this->get(route('search.suggest', ['q' => 'x']))->assertRedirect(route('login'));
    }

    public function test_suggest_returns_grouped_json(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Qzxwv Industries']);

        $this->getJson(route('search.suggest', ['q' => 'qzxwv']))
            ->assertOk()
            ->assertJsonPath('groups.0.group', 'Customers')
            ->assertJsonFragment(['title' => 'Qzxwv Industries']);
    }

    public function test_suggest_for_empty_term_returns_no_groups(): void
    {
        $this->signIn();

        $this->getJson(route('search.suggest'))->assertOk()->assertExactJson(['groups' => []]);
    }
}
