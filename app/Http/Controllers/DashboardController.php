<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The post-login landing page: quick links to the modules.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
