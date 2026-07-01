<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Settings landing page, linking to the individual settings areas.
 */
class SettingsController extends Controller
{
    public function __invoke(): View
    {
        return view('settings.index');
    }
}
