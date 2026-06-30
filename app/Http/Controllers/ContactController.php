<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactFunction;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD for a customer's contact persons.
 *
 * Routes are shallow-nested under customers: the collection actions (index,
 * create, store) are scoped to a customer, while the per-record actions
 * (show, edit, update, destroy) resolve the contact directly. Every action is
 * gated by ContactPolicy and validated by dedicated Form Requests.
 */
class ContactController extends Controller
{
    /**
     * Display a customer's contact persons.
     */
    public function index(Customer $customer): View
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = $customer->contacts()
            ->orderBy('name')
            ->paginate(15);

        return view('contacts.index', [
            'customer' => $customer,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Show the form for creating a new contact person.
     */
    public function create(Customer $customer): View
    {
        $this->authorize('create', Contact::class);

        return view('contacts.create', [
            'customer' => $customer,
            'contact' => new Contact,
            'functions' => ContactFunction::options(),
        ]);
    }

    /**
     * Store a newly created contact person for the customer.
     */
    public function store(StoreContactRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $customer->contacts()->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Contact created.');
    }

    /**
     * Display the specified contact person.
     */
    public function show(Contact $contact): View
    {
        $this->authorize('view', $contact);

        $contact->load('customer');

        return view('contacts.show', ['contact' => $contact]);
    }

    /**
     * Show the form for editing the specified contact person.
     */
    public function edit(Contact $contact): View
    {
        $this->authorize('update', $contact);

        return view('contacts.edit', [
            'contact' => $contact,
            'functions' => ContactFunction::options(),
        ]);
    }

    /**
     * Update the specified contact person.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $contact->update($request->validated());

        return redirect()
            ->route('customers.show', $contact->customer_id)
            ->with('status', 'Contact updated.');
    }

    /**
     * Remove the specified contact person.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $customerId = $contact->customer_id;
        $contact->delete();

        return redirect()
            ->route('customers.show', $customerId)
            ->with('status', 'Contact deleted.');
    }
}
