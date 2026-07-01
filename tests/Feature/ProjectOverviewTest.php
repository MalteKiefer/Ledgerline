<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_overview(): void
    {
        $this->get(route('projects.overview'))->assertRedirect(route('login'));
    }

    public function test_overview_lists_own_team_projects_across_customers(): void
    {
        $this->signIn();
        $acme = Customer::factory()->create(['name' => 'Acme Industries']);
        $globex = Customer::factory()->create(['name' => 'Globex Corporation']);
        Project::factory()->for($acme)->create(['name' => 'Acme Portal']);
        Project::factory()->for($globex)->create(['name' => 'Globex Migration']);

        $this->get(route('projects.overview'))
            ->assertOk()
            ->assertSee('Acme Portal')
            ->assertSee('Globex Migration');
    }
}
