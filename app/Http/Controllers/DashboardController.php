<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use Illuminate\Contracts\View\View;

/**
 * The post-login landing page.
 *
 * Shows summary counts and recent files for the current user's team(s). All
 * reads use the Eloquent models so the team global scope applies — a user
 * never sees anything outside their teams.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => [
                'customers' => Customer::count(),
                'projects' => Project::count(),
                'files' => File::count(),
                'storage' => (int) File::sum('size'),
            ],
            'recentFiles' => File::query()->with('attachable')->latest()->limit(5)->get(),
        ]);
    }
}
