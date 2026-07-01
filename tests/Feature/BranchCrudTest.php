<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_branches(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.branches.index', $customer))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_a_customers_branches(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        Branch::factory()->for($customer)->create(['name' => 'Berlin Office']);
        Branch::factory()->create(['name' => 'Other Office']);

        $this->get(route('customers.branches.index', $customer))
            ->assertOk()
            ->assertSee('Berlin Office')
            ->assertDontSee('Other Office');
    }

    public function test_create_form_renders(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->get(route('customers.branches.create', $customer))
            ->assertOk()
            ->assertSee('New branch')
            ->assertSee('Niederlassungsleiter');
    }

    public function test_store_creates_a_branch_with_country_and_manager(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $manager = Contact::factory()->for($customer)->create();

        $this->post(route('customers.branches.store', $customer), [
            'name' => 'Munich Office',
            'city' => 'Munich',
            'country' => 'DE',
            'manager_contact_id' => $manager->id,
        ])->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('branches', [
            'customer_id' => $customer->id,
            'name' => 'Munich Office',
            'country' => 'DE',
            'manager_contact_id' => $manager->id,
        ]);
    }

    public function test_store_rejects_an_invalid_country(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.branches.store', $customer), [
            'name' => 'Bad country',
            'country' => 'Germany',
        ])->assertSessionHasErrors('country');
    }

    public function test_store_rejects_a_manager_from_another_customer(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $foreignManager = Contact::factory()->for($otherCustomer)->create();

        $this->post(route('customers.branches.store', $customer), [
            'name' => 'Sneaky',
            'manager_contact_id' => $foreignManager->id,
        ])->assertSessionHasErrors('manager_contact_id');
    }

    public function test_update_modifies_a_branch(): void
    {
        $this->signIn();
        $branch = Branch::factory()->create(['name' => 'Old', 'country' => 'DE']);

        $this->put(route('branches.update', $branch), ['name' => 'New', 'country' => 'AT'])
            ->assertRedirect(route('customers.show', $branch->customer_id));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'New', 'country' => 'AT']);
    }

    public function test_destroy_deletes_a_branch(): void
    {
        $this->signIn();
        $branch = Branch::factory()->create();

        $this->delete(route('branches.destroy', $branch))
            ->assertRedirect(route('customers.show', $branch->customer_id));

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_deleting_a_customer_cascades_to_branches(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->for($customer)->create();

        $customer->delete();

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_deleting_a_manager_contact_nulls_the_branch_manager(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $manager = Contact::factory()->for($customer)->create();
        $branch = Branch::factory()->for($customer)->create(['manager_contact_id' => $manager->id]);

        $manager->delete();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'manager_contact_id' => null]);
    }
}
