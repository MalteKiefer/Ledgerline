<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;

/**
 * Lists every project across all customers.
 *
 * This is the customer-independent overview reachable from the main menu. The
 * per-customer project lists remain available under each customer.
 */
class ProjectOverviewController extends Controller
{
    /**
     * Display all projects with their owning customer.
     */
    public function __invoke(): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->with('customer')
            ->orderBy('name')
            ->paginate(20);

        return view('projects.overview', ['projects' => $projects]);
    }
}
