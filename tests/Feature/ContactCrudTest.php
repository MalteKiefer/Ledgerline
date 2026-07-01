<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactFunction;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_contacts(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.contacts.index', $customer))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_a_customers_contacts(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->create(['name' => 'Jane Doe']);
        Contact::factory()->create(['name' => 'Someone Else']);

        $this->get(route('customers.contacts.index', $customer))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertDontSee('Someone Else');
    }

    public function test_create_form_renders_function_options(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->get(route('customers.contacts.create', $customer))
            ->assertOk()
            ->assertSee('New contact')
            ->assertSee('Data Protection Officer');
    }

    public function test_store_creates_a_contact_with_emails_and_phones(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.contacts.store', $customer), [
            'name' => 'Alan Turing',
            'function' => ContactFunction::TECHNICAL_CONTACT->value,
            'emails' => [
                ['label' => 'Work', 'email' => 'alan@example.com'],
                ['label' => 'Private', 'email' => 'alan.t@example.com'],
            ],
            'phones' => [
                ['label' => 'Mobile', 'phone' => '+49 30 111'],
            ],
        ])->assertRedirect(route('customers.show', $customer));

        $contact = Contact::firstWhere('name', 'Alan Turing');

        $this->assertNotNull($contact);
        $this->assertSame(2, $contact->emails()->count());
        $this->assertSame(1, $contact->phones()->count());
    }

    public function test_store_strips_blank_channel_rows(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.contacts.store', $customer), [
            'name' => 'Sparse',
            'function' => ContactFunction::OTHER->value,
            'emails' => [
                ['label' => 'Work', 'email' => 'only@example.com'],
                ['label' => '', 'email' => ''],
            ],
            'phones' => [['label' => '', 'phone' => '']],
        ])->assertRedirect();

        $contact = Contact::firstWhere('name', 'Sparse');

        $this->assertSame(1, $contact->emails()->count());
        $this->assertSame(0, $contact->phones()->count());
    }

    public function test_store_rejects_an_invalid_email(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.contacts.store', $customer), [
            'name' => 'Bad email',
            'function' => ContactFunction::OTHER->value,
            'emails' => [['label' => 'Work', 'email' => 'not-an-email']],
        ])->assertSessionHasErrors('emails.0.email');
    }

    public function test_store_rejects_a_function_outside_the_enum(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('customers.contacts.store', $customer), [
            'name' => 'Mallory',
            'function' => 'SUPREME_LEADER',
        ])->assertSessionHasErrors('function');
    }

    public function test_show_displays_clickable_emails_and_phones(): void
    {
        $this->signIn();
        $contact = Contact::factory()->create(['name' => 'Ada Byron']);
        ContactEmail::factory()->for($contact)->create(['email' => 'ada@example.com']);

        $this->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Ada Byron')
            ->assertSee('mailto:ada@example.com');
    }

    public function test_update_replaces_the_channel_set(): void
    {
        $this->signIn();
        $contact = Contact::factory()->create();
        ContactEmail::factory()->count(2)->for($contact)->create();

        $this->put(route('contacts.update', $contact), [
            'name' => 'Updated Name',
            'function' => ContactFunction::SECURITY_CONTACT->value,
            'emails' => [['label' => 'Work', 'email' => 'new@example.com']],
        ])->assertRedirect(route('customers.show', $contact->customer_id));

        $this->assertSame(1, $contact->emails()->count());
    }

    public function test_destroy_deletes_a_contact(): void
    {
        $this->signIn();
        $contact = Contact::factory()->create();

        $this->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('customers.show', $contact->customer_id));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_deleting_a_customer_cascades_to_contacts(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->create();

        $customer->delete();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }
}
