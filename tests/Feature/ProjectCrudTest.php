<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_guests_cannot_access_projects(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.projects.index', $customer))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_a_customers_projects(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->create(['name' => 'Website Rebuild']);
        Project::factory()->create(['name' => 'Unrelated Project']);

        $this->actingAs($this->actingUser())
            ->get(route('customers.projects.index', $customer))
            ->assertOk()
            ->assertSee('Website Rebuild')
            ->assertDontSee('Unrelated Project');
    }

    public function test_create_form_renders_status_options(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->get(route('customers.projects.create', $customer))
            ->assertOk()
            ->assertSee('New project')
            ->assertSee('On hold');
    }

    public function test_store_creates_a_project(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.projects.store', $customer), [
                'name' => 'ERP Rollout',
                'reference' => 'PRJ-1001',
                'status' => ProjectStatus::ACTIVE->value,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'budget' => '125000.50',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('projects', [
            'customer_id' => $customer->id,
            'name' => 'ERP Rollout',
            'reference' => 'PRJ-1001',
            'status' => 'ACTIVE',
            'budget' => '125000.50',
        ]);
    }

    public function test_status_is_cast_to_the_enum_and_budget_to_decimal(): void
    {
        $project = Project::factory()->create([
            'status' => ProjectStatus::COMPLETED->value,
            'budget' => 1000,
        ]);

        $fresh = $project->fresh();

        $this->assertSame(ProjectStatus::COMPLETED, $fresh->status);
        $this->assertSame('1000.00', $fresh->budget);
    }

    public function test_store_requires_a_name(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.projects.store', $customer), [
                'name' => '',
                'status' => ProjectStatus::PLANNED->value,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Project::count());
    }

    public function test_store_rejects_a_status_outside_the_enum(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.projects.store', $customer), [
                'name' => 'Bad status',
                'status' => 'DELAYED',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Project::count());
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.projects.store', $customer), [
                'name' => 'Time travel',
                'status' => ProjectStatus::PLANNED->value,
                'start_date' => '2026-06-01',
                'end_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_reference_must_be_unique(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->create(['reference' => 'PRJ-DUP']);

        $this->actingAs($this->actingUser())
            ->post(route('customers.projects.store', $customer), [
                'name' => 'Duplicate ref',
                'status' => ProjectStatus::PLANNED->value,
                'reference' => 'PRJ-DUP',
            ])
            ->assertSessionHasErrors('reference');
    }

    public function test_update_can_keep_its_own_reference(): void
    {
        $project = Project::factory()->create([
            'reference' => 'PRJ-KEEP',
            'status' => ProjectStatus::ACTIVE->value,
        ]);

        $this->actingAs($this->actingUser())
            ->put(route('projects.update', $project), [
                'name' => 'Renamed',
                'reference' => 'PRJ-KEEP',
                'status' => ProjectStatus::ON_HOLD->value,
            ])
            ->assertRedirect(route('customers.show', $project->customer_id));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Renamed',
            'reference' => 'PRJ-KEEP',
            'status' => 'ON_HOLD',
        ]);
    }

    public function test_destroy_deletes_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->actingUser())
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('customers.show', $project->customer_id));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_deleting_a_customer_cascades_to_projects(): void
    {
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();

        $customer->delete();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_dashboard_reflects_project_count(): void
    {
        Project::factory()->count(2)->create();

        $this->actingAs($this->actingUser())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['projects'] === 2);
    }
}
