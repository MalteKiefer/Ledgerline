<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_guests_cannot_access_branches(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.branches.index', $customer))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_a_customers_branches(): void
    {
        $customer = Customer::factory()->create();
        Branch::factory()->for($customer)->create(['name' => 'Berlin Office']);
        Branch::factory()->create(['name' => 'Other Office']);

        $this->actingAs($this->actingUser())
            ->get(route('customers.branches.index', $customer))
            ->assertOk()
            ->assertSee('Berlin Office')
            ->assertDontSee('Other Office');
    }

    public function test_create_form_renders(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->get(route('customers.branches.create', $customer))
            ->assertOk()
            ->assertSee('New branch')
            ->assertSee('Niederlassungsleiter');
    }

    public function test_store_creates_a_branch_with_country_and_manager(): void
    {
        $customer = Customer::factory()->create();
        $manager = Contact::factory()->for($customer)->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.branches.store', $customer), [
                'name' => 'Munich Office',
                'city' => 'Munich',
                'country' => 'DE',
                'manager_contact_id' => $manager->id,
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('branches', [
            'customer_id' => $customer->id,
            'name' => 'Munich Office',
            'country' => 'DE',
            'manager_contact_id' => $manager->id,
        ]);
    }

    public function test_store_rejects_an_invalid_country(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.branches.store', $customer), [
                'name' => 'Bad country',
                'country' => 'Germany',
            ])
            ->assertSessionHasErrors('country');

        $this->assertSame(0, Branch::count());
    }

    public function test_store_rejects_a_manager_from_another_customer(): void
    {
        $customer = Customer::factory()->create();
        $foreignManager = Contact::factory()->create(); // belongs to a different customer

        $this->actingAs($this->actingUser())
            ->post(route('customers.branches.store', $customer), [
                'name' => 'Sneaky',
                'manager_contact_id' => $foreignManager->id,
            ])
            ->assertSessionHasErrors('manager_contact_id');

        $this->assertSame(0, Branch::count());
    }

    public function test_update_modifies_a_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Old', 'country' => 'DE']);

        $this->actingAs($this->actingUser())
            ->put(route('branches.update', $branch), [
                'name' => 'New',
                'country' => 'AT',
            ])
            ->assertRedirect(route('customers.show', $branch->customer_id));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'New',
            'country' => 'AT',
        ]);
    }

    public function test_destroy_deletes_a_branch(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->actingUser())
            ->delete(route('branches.destroy', $branch))
            ->assertRedirect(route('customers.show', $branch->customer_id));

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_deleting_a_customer_cascades_to_branches(): void
    {
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->for($customer)->create();

        $customer->delete();

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_deleting_a_manager_contact_nulls_the_branch_manager(): void
    {
        $customer = Customer::factory()->create();
        $manager = Contact::factory()->for($customer)->create();
        $branch = Branch::factory()->for($customer)->create(['manager_contact_id' => $manager->id]);

        $manager->delete();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'manager_contact_id' => null,
        ]);
    }
}
