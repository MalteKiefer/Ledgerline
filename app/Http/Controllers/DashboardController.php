<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The post-login landing page.
 *
 * Shows a few plain summary counts. The customer and project tables are
 * introduced in later milestones, so the counts are read defensively and
 * default to zero until those tables exist.
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
                'customers' => $this->countOf('customers'),
                'projects' => $this->countOf('projects'),
            ],
        ]);
    }

    /**
     * Count the rows of a table, returning zero if it does not yet exist.
     */
    private function countOf(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
