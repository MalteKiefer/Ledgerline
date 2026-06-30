<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactFunction;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
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
        Contact::factory()->for($customer)->create(['name' => 'Jane Doe']);
        Contact::factory()->create(['name' => 'Someone Else']);

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

    public function test_store_creates_a_contact_with_emails_and_phones(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.contacts.store', $customer), [
                'name' => 'Alan Turing',
                'function' => ContactFunction::TECHNICAL_CONTACT->value,
                'emails' => [
                    ['label' => 'Work', 'email' => 'alan@example.com'],
                    ['label' => 'Personal', 'email' => 'alan.t@example.com'],
                ],
                'phones' => [
                    ['label' => 'Mobile', 'phone' => '+49 30 111'],
                ],
            ])
            ->assertRedirect(route('customers.show', $customer));

        $contact = Contact::firstWhere('name', 'Alan Turing');

        $this->assertNotNull($contact);
        $this->assertSame(2, $contact->emails()->count());
        $this->assertSame(1, $contact->phones()->count());
        $this->assertDatabaseHas('contact_emails', [
            'contact_id' => $contact->id,
            'label' => 'Work',
            'email' => 'alan@example.com',
        ]);
        $this->assertDatabaseHas('contact_phones', [
            'contact_id' => $contact->id,
            'label' => 'Mobile',
            'phone' => '+49 30 111',
        ]);
    }

    public function test_store_strips_blank_channel_rows(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.contacts.store', $customer), [
                'name' => 'Sparse',
                'function' => ContactFunction::OTHER->value,
                'emails' => [
                    ['label' => 'Work', 'email' => 'only@example.com'],
                    ['label' => '', 'email' => ''],
                ],
                'phones' => [
                    ['label' => '', 'phone' => ''],
                ],
            ])
            ->assertRedirect();

        $contact = Contact::firstWhere('name', 'Sparse');

        $this->assertSame(1, $contact->emails()->count());
        $this->assertSame(0, $contact->phones()->count());
    }

    public function test_store_rejects_an_invalid_email(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->actingUser())
            ->post(route('customers.contacts.store', $customer), [
                'name' => 'Bad email',
                'function' => ContactFunction::OTHER->value,
                'emails' => [['label' => 'Work', 'email' => 'not-an-email']],
            ])
            ->assertSessionHasErrors('emails.0.email');

        $this->assertSame(0, Contact::count());
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

    public function test_show_displays_clickable_emails_and_phones(): void
    {
        $contact = Contact::factory()->create(['name' => 'Ada Byron']);
        ContactEmail::factory()->for($contact)->create(['email' => 'ada@example.com']);
        ContactPhone::factory()->for($contact)->create(['phone' => '+49 30 222']);

        $this->actingAs($this->actingUser())
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Ada Byron')
            ->assertSee('mailto:ada@example.com')
            ->assertSee('tel:+49 30 222');
    }

    public function test_update_replaces_the_channel_set(): void
    {
        $contact = Contact::factory()->create();
        ContactEmail::factory()->count(2)->for($contact)->create();

        $this->actingAs($this->actingUser())
            ->put(route('contacts.update', $contact), [
                'name' => 'Updated Name',
                'function' => ContactFunction::SECURITY_CONTACT->value,
                'emails' => [['label' => 'Work', 'email' => 'new@example.com']],
            ])
            ->assertRedirect(route('customers.show', $contact->customer_id));

        $this->assertSame(1, $contact->emails()->count());
        $this->assertDatabaseHas('contact_emails', [
            'contact_id' => $contact->id,
            'email' => 'new@example.com',
        ]);
    }

    public function test_destroy_deletes_a_contact_and_its_channels(): void
    {
        $contact = Contact::factory()->create();
        $email = ContactEmail::factory()->for($contact)->create();

        $this->actingAs($this->actingUser())
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('customers.show', $contact->customer_id));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('contact_emails', ['id' => $email->id]);
    }

    public function test_deleting_a_customer_cascades_to_contacts_and_channels(): void
    {
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->create();
        $email = ContactEmail::factory()->for($contact)->create();

        $customer->delete();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('contact_emails', ['id' => $email->id]);
    }
}
