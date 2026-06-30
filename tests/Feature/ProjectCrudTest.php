<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tag;
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

    /**
     * Build a valid project payload for the unified store endpoint.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'name' => 'A Project',
            'type' => ProjectType::DEVELOPMENT->value,
            'priority' => ProjectPriority::NORMAL->value,
            'status' => ProjectStatus::PLANNED->value,
        ], $overrides);
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

    public function test_global_create_form_renders_with_a_customer_picker(): void
    {
        $this->actingAs($this->actingUser())
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('New project')
            ->assertSee('Customer')
            ->assertSee('Development');
    }

    public function test_create_form_locks_a_preset_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Preset Co']);

        $this->actingAs($this->actingUser())
            ->get(route('projects.create', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Preset Co');
    }

    public function test_store_creates_a_project_with_type_priority_and_tags(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, [
                'name' => 'ERP Rollout',
                'reference' => 'PRJ-1001',
                'type' => ProjectType::CONSULTING->value,
                'priority' => ProjectPriority::HIGH->value,
                'status' => ProjectStatus::ACTIVE->value,
                'estimated_hours' => '120.5',
                'tags' => ['AWS', 'Migration'],
            ]))
            ->assertRedirect(route('customers.show', $customer));

        $project = Project::firstWhere('name', 'ERP Rollout');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'customer_id' => $customer->id,
            'type' => 'CONSULTING',
            'priority' => 'HIGH',
            'status' => 'ACTIVE',
        ]);
        $this->assertEqualsCanonicalizing(['AWS', 'Migration'], $project->tags->pluck('name')->all());
    }

    public function test_store_deduplicates_tags_case_insensitively(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, [
                'tags' => ['AWS', 'aws', ' AWS ', 'Firewall'],
            ]));

        $this->assertSame(2, Tag::count());
        $this->assertSame(2, Project::firstWhere('customer_id', $customer->id)->tags()->count());
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

    public function test_store_requires_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $payload = $this->payload($customer);
        unset($payload['customer_id']);

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $payload)
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(0, Project::count());
    }

    public function test_store_requires_a_name(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, ['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Project::count());
    }

    public function test_store_rejects_a_type_outside_the_enum(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, ['type' => 'TELEPATHY']))
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Project::count());
    }

    public function test_store_rejects_a_status_outside_the_enum(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, ['status' => 'DELAYED']))
            ->assertSessionHasErrors('status');
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, [
                'start_date' => '2026-06-01',
                'end_date' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_reference_must_be_unique(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->create(['reference' => 'PRJ-DUP']);

        $this->actingAs($this->actingUser())
            ->post(route('projects.store'), $this->payload($customer, ['reference' => 'PRJ-DUP']))
            ->assertSessionHasErrors('reference');
    }

    public function test_update_changes_type_and_replaces_tags(): void
    {
        $project = Project::factory()->create([
            'reference' => 'PRJ-KEEP',
            'type' => ProjectType::DEVELOPMENT->value,
        ]);
        $project->tags()->attach(Tag::findOrCreateByName('Old')->id);

        $this->actingAs($this->actingUser())
            ->put(route('projects.update', $project), [
                'name' => 'Renamed',
                'reference' => 'PRJ-KEEP',
                'type' => ProjectType::MAINTENANCE->value,
                'priority' => ProjectPriority::URGENT->value,
                'status' => ProjectStatus::ON_HOLD->value,
                'tags' => ['New'],
            ])
            ->assertRedirect(route('customers.show', $project->customer_id));

        $project->refresh();
        $this->assertSame(ProjectType::MAINTENANCE, $project->type);
        $this->assertSame(['New'], $project->tags->pluck('name')->all());
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

    public function test_overview_filters_by_type(): void
    {
        Project::factory()->create(['name' => 'Dev One', 'type' => ProjectType::DEVELOPMENT->value]);
        Project::factory()->create(['name' => 'Net One', 'type' => ProjectType::NETWORK->value]);

        $this->actingAs($this->actingUser())
            ->get(route('projects.overview', ['type' => ProjectType::DEVELOPMENT->value]))
            ->assertOk()
            ->assertSee('Dev One')
            ->assertDontSee('Net One');
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
