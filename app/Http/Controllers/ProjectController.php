<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD for a customer's projects.
 *
 * Routes are shallow-nested under customers: the collection actions are scoped
 * to a customer, while per-record actions resolve the project directly. Every
 * action is gated by ProjectPolicy and validated by dedicated Form Requests.
 */
class ProjectController extends Controller
{
    /**
     * Display a customer's projects.
     */
    public function index(Customer $customer): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = $customer->projects()
            ->orderBy('name')
            ->paginate(15);

        return view('projects.index', [
            'customer' => $customer,
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(Customer $customer): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create', [
            'customer' => $customer,
            'project' => new Project,
            'statuses' => ProjectStatus::options(),
        ]);
    }

    /**
     * Store a newly created project for the customer.
     */
    public function store(StoreProjectRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $customer->projects()->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Project created.');
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load('customer');

        return view('projects.show', ['project' => $project]);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            'project' => $project,
            'statuses' => ProjectStatus::options(),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()
            ->route('customers.show', $project->customer_id)
            ->with('status', 'Project updated.');
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $customerId = $project->customer_id;
        $project->delete();

        return redirect()
            ->route('customers.show', $customerId)
            ->with('status', 'Project deleted.');
    }
}
