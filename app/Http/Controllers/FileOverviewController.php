<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Models\Customer;
use App\Models\File;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A single, explorer-style document view for every file: a folder tree, a
 * breadcrumb, search, type/tag/record filters, and multi-file upload. Files
 * attached to a customer or project are reached from here via a record filter,
 * so there is one place for documents rather than nested per-record panels.
 */
class FileOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', File::class);

        $folder = $request->query('folder')
            ? Folder::with('parent')->findOrFail($request->query('folder'))
            : null;

        $type = FileType::tryFrom((string) $request->query('type'));
        $tagSlug = $request->query('tag');
        $term = $request->query('q');
        $customer = $request->query('customer') ? Customer::find($request->query('customer')) : null;
        $project = $request->query('project') ? Project::find($request->query('project')) : null;

        // A search/filter (text, type, tag, or record) switches from folder
        // browsing to a flat result set across all folders.
        $filtering = filled($term) || $type !== null || filled($tagSlug) || $customer !== null || $project !== null;

        [$sort, $dir] = $this->sortFor($request, ['name', 'type', 'size', 'created_at'], 'created_at');

        if ($request->query('sort') === null && $request->query('dir') === null) {
            $dir = 'desc';
        }

        $files = File::query()
            ->with(['attachable', 'tags'])
            ->when(! $filtering, fn ($query) => $query->where('folder_id', $folder?->id))
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->when($tagSlug, fn ($query) => $query->whereHas('tags', fn ($t) => $t->where('slug', $tagSlug)))
            ->when($customer, fn ($query) => $query->where('attachable_type', Customer::class)->where('attachable_id', $customer->id))
            ->when($project, fn ($query) => $query->where('attachable_type', Project::class)->where('attachable_id', $project->id))
            ->when($term, function ($query, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $query->where(function ($where) use ($like): void {
                    $where->orWhereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(extracted_text) LIKE ?', [$like]);
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(30)
            ->withQueryString();

        $subfolders = $filtering
            ? collect()
            : Folder::query()->where('parent_id', $folder?->id)->withCount('files')->orderBy('name')->get();

        return view('files.index', [
            'files' => $files,
            'folder' => $folder,
            'subfolders' => $subfolders,
            'tree' => $this->tree(),
            'breadcrumb' => $folder ? $folder->ancestors()->push($folder) : collect(),
            'filtering' => $filtering,
            'types' => FileType::options(),
            'activeType' => $type?->value,
            'activeTagSlug' => $tagSlug,
            'activeTagName' => $tagSlug ? Tag::where('slug', $tagSlug)->value('name') : null,
            'recordFilter' => $this->recordFilterLabel($customer, $project),
            'sort' => $sort,
            'dir' => $dir,
            'targets' => $this->targetOptions(),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
        ]);
    }

    /**
     * The full folder set as a nested tree for the sidebar and the move modal.
     *
     * @return Collection<int, Folder>
     */
    private function tree(): Collection
    {
        $all = Folder::query()->withCount('files')->orderBy('name')->get();
        $byParent = $all->groupBy('parent_id');

        $attach = function (Folder $node) use (&$attach, $byParent): Folder {
            $node->setRelation('children', ($byParent->get($node->id) ?? collect())->map($attach)->values());

            return $node;
        };

        return ($byParent->get(null) ?? collect())->map($attach)->values();
    }

    private function recordFilterLabel(?Customer $customer, ?Project $project): ?string
    {
        if ($customer !== null) {
            return __('files.location_customer', ['name' => $customer->name]);
        }

        if ($project !== null) {
            return __('files.location_project', ['name' => $project->name]);
        }

        return null;
    }

    /**
     * Build the "customer:<id>" / "project:<id>" options for the upload target.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function targetOptions(): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Customer $c): array => ['value' => 'customer:'.$c->id, 'label' => 'Customer: '.$c->name]);

        $projects = Project::query()->with('customer')->orderBy('name')->get()
            ->map(fn (Project $p): array => ['value' => 'project:'.$p->id, 'label' => 'Project: '.$p->name.' ('.$p->customer->name.')']);

        return $customers->concat($projects)->values()->all();
    }
}
