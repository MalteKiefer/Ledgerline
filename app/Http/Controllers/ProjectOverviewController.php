<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lists every project across all customers, with an optional type filter.
 *
 * This is the customer-independent overview reachable from the main menu. The
 * per-customer project lists remain available under each customer.
 */
class ProjectOverviewController extends Controller
{
    /**
     * Display all projects with their owning customer.
     */
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $type = ProjectType::tryFrom((string) $request->query('type'));

        $projects = Project::query()
            ->with(['customer', 'tags'])
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('projects.overview', [
            'projects' => $projects,
            'types' => ProjectType::options(),
            'activeType' => $type?->value,
        ]);
    }
}
