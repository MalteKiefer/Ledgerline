<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactFunction;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_guests_cannot_access_contacts(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.contacts.index', $customer))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_a_customers_contacts(): void
    {
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->create(['name' => 'Jane Doe']);
        $other = Contact::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($this->actingUser())
            ->get(route('customers.contacts.index', $customer))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertDontSee('Someone Else');
    }

    public function test_create_form_renders_function_options(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->get(route('customers.contacts.create', $customer))
            ->assertOk()
            ->assertSee('New contact')
            ->assertSee('Data Protection Officer');
    }

    public function test_store_creates_a_contact_with_a_valid_function(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.contacts.store', $customer), [
                'name' => 'Alan Turing',
                'email' => 'alan@example.com',
                'function' => ContactFunction::TECHNICAL_CONTACT->value,
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'name' => 'Alan Turing',
            'function' => 'TECHNICAL_CONTACT',
        ]);
    }

    public function test_function_is_cast_to_the_enum(): void
    {
        $contact = Contact::factory()->create(['function' => ContactFunction::CEO->value]);

        $this->assertInstanceOf(ContactFunction::class, $contact->fresh()->function);
        $this->assertSame(ContactFunction::CEO, $contact->fresh()->function);
    }

    public function test_store_requires_a_name(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.contacts.store', $customer), [
                'name' => '',
                'function' => ContactFunction::CEO->value,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Contact::count());
    }

    public function test_store_rejects_a_function_outside_the_enum(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.contacts.store', $customer), [
                'name' => 'Mallory',
                'function' => 'SUPREME_LEADER',
            ])
            ->assertSessionHasErrors('function');

        $this->assertSame(0, Contact::count());
    }

    public function test_show_displays_a_contact(): void
    {
        $contact = Contact::factory()->create([
            'name' => 'Ada Byron',
            'function' => ContactFunction::PROJECT_MANAGER->value,
        ]);

        $this->actingAs($this->actingUser())
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Ada Byron')
            ->assertSee('Project Manager');
    }

    public function test_update_modifies_a_contact(): void
    {
        $contact = Contact::factory()->create(['function' => ContactFunction::HELPDESK->value]);

        $this->actingAs($this->actingUser())
            ->put(route('contacts.update', $contact), [
                'name' => 'Updated Name',
                'function' => ContactFunction::SECURITY_CONTACT->value,
            ])
            ->assertRedirect(route('customers.show', $contact->customer_id));

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Updated Name',
            'function' => 'SECURITY_CONTACT',
        ]);
    }

    public function test_destroy_deletes_a_contact(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->actingUser())
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('customers.show', $contact->customer_id));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_deleting_a_customer_cascades_to_contacts(): void
    {
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->create();

        $customer->delete();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }
}
