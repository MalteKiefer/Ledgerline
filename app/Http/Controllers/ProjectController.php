<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD for projects.
 *
 * Listing is scoped per customer; creation and editing use one unified form.
 * When creating from a customer the customer is fixed; from the global Projects
 * overview a customer is chosen in the form. Every action is gated by
 * ProjectPolicy and validated by dedicated Form Requests.
 */
class ProjectController extends Controller
{
    /**
     * Display a customer's projects.
     */
    public function index(Request $request, Customer $customer): View
    {
        $this->authorize('viewAny', Project::class);

        [$sort, $dir] = $this->sortFor($request, ['name', 'reference', 'type', 'status'], 'name');

        $projects = $customer->projects()
            ->with('tags')
            ->when($request->query('q'), function ($query, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $query->where(function ($where) use ($like): void {
                    $where->orWhereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(reference) LIKE ?', [$like]);
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', [
            'customer' => $customer,
            'projects' => $projects,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * Show the unified create form, optionally locked to a preset customer.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);

        $customerId = $request->query('customer');
        $lockedCustomer = $customerId ? Customer::find($customerId) : null;

        return view('projects.create', [
            'project' => new Project,
            'lockedCustomer' => $lockedCustomer,
            'customers' => $lockedCustomer ? collect() : Customer::orderBy('name')->get(['id', 'name']),
            'existingTags' => [],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validated();
        $customer = Customer::findOrFail($data['customer_id']);
        $tags = $data['tags'] ?? [];
        unset($data['customer_id'], $data['tags']);

        DB::transaction(function () use ($customer, $data, $tags): void {
            $project = $customer->projects()->create($data);
            $this->syncTags($project, $tags);
        });

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('flash.project_created'));
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['customer', 'tags', 'files' => fn ($query) => $query->with('tags')->latest()]);

        return view('projects.show', [
            'project' => $project,
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
        ]);
    }

    /**
     * Show the form for editing the specified project (customer fixed).
     */
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        $project->load(['customer', 'tags']);

        return view('projects.edit', [
            'project' => $project,
            'lockedCustomer' => $project->customer,
            'customers' => collect(),
            'existingTags' => $project->tags->pluck('name')->all(),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        DB::transaction(function () use ($project, $data, $tags): void {
            $project->update($data);
            $this->syncTags($project, $tags);
        });

        return redirect()
            ->route('customers.show', $project->customer_id)
            ->with('status', __('flash.project_updated'));
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
            ->with('status', __('flash.project_deleted'));
    }

    /**
     * Shared option lists for the project form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => ProjectStatus::options(),
            'types' => ProjectType::options(),
            'priorities' => ProjectPriority::options(),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
        ];
    }

    /**
     * Resolve tag names to (deduplicated) tag rows and sync them.
     *
     * @param  list<string>  $names
     */
    private function syncTags(Project $project, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->all();

        $project->tags()->sync($ids);
    }
}
