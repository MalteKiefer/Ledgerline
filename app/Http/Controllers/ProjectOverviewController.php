<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lists every project across all customers, with search, sort and a type
 * filter. The per-customer project lists remain available under each customer.
 */
class ProjectOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $type = ProjectType::tryFrom((string) $request->query('type'));
        [$sort, $dir] = $this->sortFor($request, ['name', 'reference', 'type', 'status'], 'name');

        $projects = Project::query()
            ->with(['customer', 'tags'])
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->when($request->query('q'), function ($query, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $query->where(function ($where) use ($like): void {
                    $where->orWhereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(reference) LIKE ?', [$like]);
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('projects.overview', [
            'projects' => $projects,
            'types' => ProjectType::options(),
            'activeType' => $type?->value,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }
}
