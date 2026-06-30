<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD for a customer's branch offices (Niederlassungen).
 *
 * Routes are shallow-nested under customers. A branch may name one of the
 * customer's contacts as its manager. Every action is gated by BranchPolicy
 * and validated by dedicated Form Requests.
 */
class BranchController extends Controller
{
    /**
     * Display a customer's branches.
     */
    public function index(Customer $customer): View
    {
        $this->authorize('viewAny', Branch::class);

        $branches = $customer->branches()
            ->with('manager')
            ->orderBy('name')
            ->paginate(15);

        return view('branches.index', [
            'customer' => $customer,
            'branches' => $branches,
        ]);
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(Customer $customer): View
    {
        $this->authorize('create', Branch::class);

        return view('branches.create', [
            'customer' => $customer,
            'branch' => new Branch,
            'contacts' => $customer->contacts()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created branch for the customer.
     */
    public function store(StoreBranchRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $customer->branches()->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Branch created.');
    }

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        $branch->load(['customer', 'manager']);

        return view('branches.show', ['branch' => $branch]);
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        $branch->load('customer');

        return view('branches.edit', [
            'branch' => $branch,
            'contacts' => $branch->customer->contacts()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        return redirect()
            ->route('customers.show', $branch->customer_id)
            ->with('status', 'Branch updated.');
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $customerId = $branch->customer_id;
        $branch->delete();

        return redirect()
            ->route('customers.show', $customerId)
            ->with('status', 'Branch deleted.');
    }
}
