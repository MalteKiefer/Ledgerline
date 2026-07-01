<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_customers(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_customers(): void
    {
        $this->signIn();
        Customer::factory()->create(['name' => 'Acme Industries']);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Acme Industries');
    }

    public function test_create_form_renders(): void
    {
        $this->signIn();

        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('New customer');
    }

    public function test_store_creates_a_customer(): void
    {
        $this->signIn();

        $response = $this->post(route('customers.store'), [
            'name' => 'Globex Corporation',
            'email' => 'contact@globex.test',
            'city' => 'Berlin',
        ]);

        $customer = Customer::firstWhere('name', 'Globex Corporation');

        $this->assertNotNull($customer);
        $response->assertRedirect(route('customers.show', $customer));
    }

    public function test_store_requires_a_name(): void
    {
        $this->signIn();

        $this->post(route('customers.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Customer::count());
    }

    public function test_store_rejects_an_invalid_email(): void
    {
        $this->signIn();

        $this->post(route('customers.store'), ['name' => 'Valid', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_show_displays_a_customer(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['name' => 'Initech']);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Initech');
    }

    public function test_update_modifies_a_customer(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $this->put(route('customers.update', $customer), ['name' => 'New Name', 'city' => 'Hamburg'])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name', 'city' => 'Hamburg']);
    }

    public function test_destroy_deletes_a_customer(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_dashboard_counts_customers(): void
    {
        $this->signIn();
        Customer::factory()->count(3)->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['customers'] === 3 && $stats['projects'] === 0);
    }

    public function test_store_accepts_website_and_iso_country(): void
    {
        $this->signIn();

        $this->post(route('customers.store'), [
            'name' => 'Country Co',
            'website' => 'https://country.test',
            'country' => 'DE',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Country Co',
            'website' => 'https://country.test',
            'country' => 'DE',
        ]);
    }

    public function test_store_rejects_an_invalid_country_code(): void
    {
        $this->signIn();

        $this->post(route('customers.store'), ['name' => 'Bad Country', 'country' => 'Germany'])
            ->assertSessionHasErrors('country');
    }

    public function test_store_rejects_an_invalid_website(): void
    {
        $this->signIn();

        $this->post(route('customers.store'), ['name' => 'Bad Website', 'website' => 'not a url'])
            ->assertSessionHasErrors('website');
    }
}
