<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Contracts\View\View;

/**
 * The post-login landing page: gallery summary counts and quick links to the
 * modules.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'gallery' => Photo::counts(),
        ]);
    }
}
