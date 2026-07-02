<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Photo;
use Illuminate\Contracts\View\View;

/**
 * The post-login landing page: file and gallery summary counts plus recent files.
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
                'files' => File::count(),
                'storage' => (int) File::sum('size'),
            ],
            'gallery' => Photo::counts(),
            'recentFiles' => File::query()->latest()->limit(5)->get(),
        ]);
    }
}
