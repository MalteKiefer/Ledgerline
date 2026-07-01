<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactFunction;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD for a customer's contact persons.
 *
 * Routes are shallow-nested under customers. A contact may carry any number of
 * labelled email addresses and phone numbers, persisted in related tables and
 * synced atomically. Every action is gated by ContactPolicy and validated by
 * dedicated Form Requests.
 */
class ContactController extends Controller
{
    /**
     * Display a customer's contact persons.
     */
    public function index(Request $request, Customer $customer): View
    {
        $this->authorize('viewAny', Contact::class);

        [$sort, $dir] = $this->sortFor($request, ['name', 'function'], 'name');

        $contacts = $customer->contacts()
            ->with(['emails', 'phones'])
            ->when($request->query('q'), function ($query, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $query->where(function ($where) use ($like): void {
                    $where->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereHas('emails', fn ($e) => $e->whereRaw('LOWER(email) LIKE ?', [$like]))
                        ->orWhereHas('phones', fn ($p) => $p->whereRaw('LOWER(phone) LIKE ?', [$like]));
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('contacts.index', [
            'customer' => $customer,
            'contacts' => $contacts,
            'sort' => $sort,
            'dir' => $dir,
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
            'emailLabels' => ContactEmail::SUGGESTED_LABELS,
            'phoneLabels' => ContactPhone::SUGGESTED_LABELS,
            'existingEmails' => [],
            'existingPhones' => [],
        ]);
    }

    /**
     * Store a newly created contact person for the customer.
     */
    public function store(StoreContactRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $data = $request->validated();

        DB::transaction(function () use ($customer, $data): void {
            $contact = $customer->contacts()->create([
                'name' => $data['name'],
                'function' => $data['function'],
            ]);

            $this->syncChannels($contact, $data);
        });

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('flash.contact_created'));
    }

    /**
     * Display the specified contact person.
     */
    public function show(Contact $contact): View
    {
        $this->authorize('view', $contact);

        $contact->load(['customer', 'emails', 'phones']);

        return view('contacts.show', ['contact' => $contact]);
    }

    /**
     * Show the form for editing the specified contact person.
     */
    public function edit(Contact $contact): View
    {
        $this->authorize('update', $contact);

        $contact->load(['emails', 'phones']);

        return view('contacts.edit', [
            'contact' => $contact,
            'functions' => ContactFunction::options(),
            'emailLabels' => ContactEmail::SUGGESTED_LABELS,
            'phoneLabels' => ContactPhone::SUGGESTED_LABELS,
            'existingEmails' => $contact->emails
                ->map(fn (ContactEmail $email): array => ['label' => $email->label, 'value' => $email->email])
                ->all(),
            'existingPhones' => $contact->phones
                ->map(fn (ContactPhone $phone): array => ['label' => $phone->label, 'value' => $phone->phone])
                ->all(),
        ]);
    }

    /**
     * Update the specified contact person.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $data = $request->validated();

        DB::transaction(function () use ($contact, $data): void {
            $contact->update([
                'name' => $data['name'],
                'function' => $data['function'],
            ]);

            // Replace the channel sets wholesale; the form submits the full list.
            $contact->emails()->delete();
            $contact->phones()->delete();

            $this->syncChannels($contact, $data);
        });

        return redirect()
            ->route('customers.show', $contact->customer_id)
            ->with('status', __('flash.contact_updated'));
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
            ->with('status', __('flash.contact_deleted'));
    }

    /**
     * Persist the contact's labelled emails and phones from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncChannels(Contact $contact, array $data): void
    {
        foreach ($data['emails'] ?? [] as $row) {
            $contact->emails()->create(['label' => $row['label'], 'email' => $row['email']]);
        }

        foreach ($data['phones'] ?? [] as $row) {
            $contact->phones()->create(['label' => $row['label'], 'phone' => $row['phone']]);
        }
    }
}
