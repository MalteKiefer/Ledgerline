<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Contracts\View\View;

/**
 * The post-login landing page.
 *
 * Shows a few plain summary counts for the current user's team(s). The counts
 * use the Eloquent models so the team global scope applies — a user never sees
 * counts for data outside their teams.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard with summary counts.
     */
    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => [
                'customers' => Customer::count(),
                'projects' => Project::count(),
            ],
        ]);
    }
}
