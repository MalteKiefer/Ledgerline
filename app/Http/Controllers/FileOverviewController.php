<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Models\File;
use App\Models\Folder;
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

        // A search/filter (text, type, tag) switches from folder browsing to a
        // flat result set across all folders.
        $filtering = filled($term) || $type !== null || filled($tagSlug);

        // Default to an alphabetical (A–Z) listing.
        [$sort, $dir] = $this->sortFor($request, ['name', 'type', 'size', 'created_at'], 'name');

        $files = File::query()
            ->with(['tags'])
            ->when(! $filtering, fn ($query) => $query->where('folder_id', $folder?->id))
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
            ->paginate(30)
            ->withQueryString();

        $subfolders = $filtering
            ? collect()
            : Folder::query()->with('tags')->where('parent_id', $folder?->id)->withCount('files')->orderBy('name')->get();

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
            'sort' => $sort,
            'dir' => $dir,
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
}
