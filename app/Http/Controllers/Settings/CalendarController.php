<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ProvidesDavSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CalendarRequest;
use App\Models\UserSetting;
use App\Services\Calendar\ContactDerivedCalendars;
use App\Services\Calendar\HolidayCalendarBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Personal calendar settings (per user): display + behaviour preferences, the
 * generated birthdays/anniversaries/holidays calendars, and the CalDAV sync.
 */
class CalendarController extends Controller
{
    use ProvidesDavSync;

    public function edit(Request $request): View
    {
        return view('settings.calendar.edit', [
            'settings' => UserSetting::for($request->user()->id),
            'countries' => $this->countryChoices(),
            'timezones' => timezone_identifiers_list(),
            ...$this->davSync($request->user()->id),
        ]);
    }

    public function update(CalendarRequest $request, ContactDerivedCalendars $derived, HolidayCalendarBuilder $holidays): RedirectResponse
    {
        $userId = $request->user()->id;
        UserSetting::for($userId)->update($request->validated());

        // Reconcile only this user's generated calendars with their toggles.
        $derived->sync($userId);
        $holidays->sync(null, $userId);

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
