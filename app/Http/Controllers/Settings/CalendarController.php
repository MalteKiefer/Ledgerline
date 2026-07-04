<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CalendarRequest;
use App\Models\AppSettings;
use App\Services\Calendar\ContactDerivedCalendars;
use App\Services\Calendar\HolidayCalendarBuilder;
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
        return view('settings.calendar.edit', [
            'settings' => AppSettings::current(),
            'countries' => $this->countryChoices(),
        ]);
    }

    public function update(CalendarRequest $request, ContactDerivedCalendars $derived, HolidayCalendarBuilder $holidays): RedirectResponse
    {
        AppSettings::current()->update($request->validated());

        // Reconcile the generated calendars with the saved toggles / countries.
        $derived->sync();
        $holidays->sync();

        return redirect()->route('settings.calendar.edit')->with('status', __('flash.calendar_settings_saved'));
    }

    /**
     * The offered holiday countries as code => localised name, sorted by name.
     *
     * @return array<string, string>
     */
    private function countryChoices(): array
    {
        $locale = app()->getLocale();
        $choices = [];
        foreach (HolidayCalendarBuilder::COUNTRIES as $code) {
            $choices[$code] = \Locale::getDisplayRegion('-'.$code, $locale) ?: $code;
        }
        asort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }
}
