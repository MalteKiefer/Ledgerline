<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_guests_cannot_access_customers(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_customers(): void
    {
        $customer = Customer::factory()->create(['name' => 'Acme Industries']);

        $this->actingAs($this->actingUser())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Acme Industries');
    }

    public function test_create_form_renders(): void
    {
        $this->actingAs($this->actingUser())
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('New customer');
    }

    public function test_store_creates_a_customer(): void
    {
        $payload = [
            'name' => 'Globex Corporation',
            'email' => 'contact@globex.test',
            'phone' => '+49 30 1234567',
            'city' => 'Berlin',
        ];

        $response = $this->actingAs($this->actingUser())
            ->post(route('customers.store'), $payload);

        $customer = Customer::firstWhere('name', 'Globex Corporation');

        $this->assertNotNull($customer);
        $response->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', [
            'name' => 'Globex Corporation',
            'email' => 'contact@globex.test',
            'city' => 'Berlin',
        ]);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingAs($this->actingUser())
            ->post(route('customers.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Customer::count());
    }

    public function test_store_rejects_an_invalid_email(): void
    {
        $this->actingAs($this->actingUser())
            ->post(route('customers.store'), ['name' => 'Valid', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_show_displays_a_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Initech']);

        $this->actingAs($this->actingUser())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Initech');
    }

    public function test_update_modifies_a_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->actingUser())
            ->put(route('customers.update', $customer), [
                'name' => 'New Name',
                'city' => 'Hamburg',
            ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name',
            'city' => 'Hamburg',
        ]);
    }

    public function test_destroy_deletes_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_dashboard_reflects_customer_count(): void
    {
        Customer::factory()->count(3)->create();

        $this->actingAs($this->actingUser())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', [
                'customers' => 3,
                'projects' => 0,
            ]);
    }

    public function test_store_accepts_website_and_iso_country(): void
    {
        $this->actingAs($this->actingUser())
            ->post(route('customers.store'), [
                'name' => 'Country Co',
                'website' => 'https://country.test',
                'country' => 'DE',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Country Co',
            'website' => 'https://country.test',
            'country' => 'DE',
        ]);
    }

    public function test_store_rejects_an_invalid_country_code(): void
    {
        $this->actingAs($this->actingUser())
            ->post(route('customers.store'), [
                'name' => 'Bad Country',
                'country' => 'Germany',
            ])
            ->assertSessionHasErrors('country');

        $this->assertSame(0, Customer::count());
    }

    public function test_store_rejects_an_invalid_website(): void
    {
        $this->actingAs($this->actingUser())
            ->post(route('customers.store'), [
                'name' => 'Bad Website',
                'website' => 'not a url',
            ])
            ->assertSessionHasErrors('website');
    }
}
