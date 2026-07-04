<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CalendarRequest;
use App\Models\AppSettings;
use App\Services\Calendar\ContactDerivedCalendars;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Calendar settings: display + behaviour preferences (week start, week numbers,
 * default event duration).
 */
class CalendarController extends Controller
{
    public function edit(): View
    {
        return view('settings.calendar.edit', ['settings' => AppSettings::current()]);
    }

    public function update(CalendarRequest $request, ContactDerivedCalendars $derived): RedirectResponse
    {
        AppSettings::current()->update($request->validated());

        // Reconcile the derived birthdays/anniversaries calendars with the toggles.
        $derived->sync();

        return redirect()->route('settings.calendar.edit')->with('status', __('flash.calendar_settings_saved'));
    }
}
