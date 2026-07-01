<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for customer records.
 *
 * Every action is gated by CustomerPolicy. Input is validated by dedicated
 * Form Requests, and writes use only validated, mass-assignable data.
 */
class CustomerController extends Controller
{
    /**
     * Display a paginated listing of customers.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        [$sort, $dir] = $this->sortFor($request, ['name', 'email', 'city', 'country'], 'name');

        $customers = Customer::query()
            ->when($request->query('q'), function ($query, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $query->where(function ($where) use ($like): void {
                    foreach (['name', 'email', 'phone', 'city', 'postal_code', 'street', 'country', 'vat_id'] as $column) {
                        $where->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                    }
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', ['customer' => new Customer]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Customer created.');
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        // Eager load related records to render the lists without N+1 queries.
        $customer->load([
            'contacts' => fn ($query) => $query->with(['emails', 'phones'])->orderBy('name'),
            'projects' => fn ($query) => $query->orderBy('name'),
            'branches' => fn ($query) => $query->with('manager')->orderBy('name'),
            'files' => fn ($query) => $query->with('tags')->latest(),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
        ]);
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', ['customer' => $customer]);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Customer updated.');
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer deleted.');
    }
}
