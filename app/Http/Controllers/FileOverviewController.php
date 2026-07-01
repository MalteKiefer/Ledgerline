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

/**
 * A folder-based browser over every file, with search, sort, type and tag
 * filters, folder navigation, and general/company file upload.
 *
 * While browsing, only the current folder's subfolders and files are shown.
 * Searching or filtering switches to a flat view across all folders.
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
        $searching = filled($term) || $type !== null || filled($tagSlug);

        [$sort, $dir] = $this->sortFor($request, ['name', 'type', 'size', 'created_at'], 'created_at');

        if ($request->query('sort') === null && $request->query('dir') === null) {
            $dir = 'desc';
        }

        $files = File::query()
            ->with(['attachable', 'tags'])
            ->when(! $searching, fn ($query) => $query->where('folder_id', $folder?->id))
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->when($tagSlug, fn ($query) => $query->whereHas('tags', fn ($t) => $t->where('slug', $tagSlug)))
            ->when($term, function ($query, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $query->where(function ($where) use ($like): void {
                    $where->orWhereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(extracted_text) LIKE ?', [$like]);
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        $subfolders = $searching
            ? collect()
            : Folder::query()->where('parent_id', $folder?->id)->withCount('files')->orderBy('name')->get();

        $breadcrumb = $folder ? $folder->ancestors()->push($folder) : collect();

        return view('files.index', [
            'files' => $files,
            'folder' => $folder,
            'subfolders' => $subfolders,
            'breadcrumb' => $breadcrumb,
            'searching' => $searching,
            'types' => FileType::options(),
            'activeType' => $type?->value,
            'activeTagSlug' => $tagSlug,
            'activeTagName' => $tagSlug ? Tag::where('slug', $tagSlug)->value('name') : null,
            'sort' => $sort,
            'dir' => $dir,
            'targets' => $this->targetOptions(),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
        ]);
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
