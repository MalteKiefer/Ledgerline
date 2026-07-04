<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\AppSettings;
use App\Models\Calendar;
use App\Support\WorkspaceOwners;
use Yasumi\Yasumi;

/**
 * Materialises public holidays for the selected countries (via Yasumi) into a
 * read-only "Holidays" calendar. Holidays are concrete all-day events per year
 * (Easter etc. move), generated for a rolling window of years.
 */
class HolidayCalendarBuilder
{
    /** Countries offered in the settings (ISO 3166-2), all supported by Yasumi. */
    public const COUNTRIES = [
        'DE', 'AT', 'CH', 'US', 'GB', 'FR', 'IT', 'ES', 'NL', 'BE',
        'DK', 'SE', 'NO', 'FI', 'PL', 'CZ', 'PT', 'IE', 'AU', 'CA',
    ];

    public function __construct(
        private readonly ICalService $ical,
        private readonly CalendarObjectPersister $persister,
    ) {}

    /** Reconcile the holidays calendar for every workspace user. */
    public function sync(?int $currentYear = null): void
    {
        $countries = $this->validCountries(AppSettings::current()->calendar_holiday_countries ?? []);

        foreach (WorkspaceOwners::userIds() as $userId) {
            $this->reconcile($userId, $countries, $currentYear);
        }
    }

    /**
     * @param  list<string>  $countries
     */
    private function reconcile(int $userId, array $countries, ?int $currentYear): void
    {
        if ($countries === []) {
            Calendar::where('user_id', $userId)->where('uri', 'holidays')->get()->each->delete();

            return;
        }

        $calendar = Calendar::firstOrCreate(
            ['user_id' => $userId, 'uri' => 'holidays'],
            ['name' => __('calendar.ui.holidays_calendar'), 'color' => '#0891b2', 'components' => ['VEVENT'], 'synctoken' => 1, 'read_only' => true],
        );

        $this->rebuild($calendar, $countries, $currentYear);
    }

    /**
     * @param  list<string>  $countries
     */
    private function rebuild(Calendar $calendar, array $countries, ?int $currentYear): void
    {
        $locale = str_starts_with(app()->getLocale(), 'de') ? 'de' : 'en';
        $multi = count($countries) > 1;
        // A rolling window: last year through two years ahead.
        $years = range($currentYear ?? (int) date('Y') - 1, ($currentYear ?? (int) date('Y')) + 2);

        // Stable uri per (country, date, name) so a re-run is a no-op diff.
        $map = [];
        foreach ($countries as $country) {
            foreach ($years as $year) {
                foreach ($this->holidays($country, $year, $locale) as $holiday) {
                    $title = $multi ? $holiday['name'].' ('.$country.')' : $holiday['name'];
                    $key = sha1($country.'|'.$holiday['date'].'|'.$holiday['name']);
                    // Stable UID → identical ICS on re-run → no-op diff.
                    $ics = $this->ical->buildEvent(['summary' => $title, 'start' => $holiday['date'], 'all_day' => true], 'll-holiday-'.$key);
                    $map[$key.'.ics'] = $ics;
                }
            }
        }

        $this->persister->replace($calendar, $map);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private function holidays(string $country, int $year, string $locale): array
    {
        try {
            $provider = Yasumi::createByISO3166_2($country, $year, $locale);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($provider->getHolidays() as $holiday) {
            $out[] = ['date' => $holiday->format('Y-m-d'), 'name' => $holiday->getName()];
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $countries
     * @return list<string>
     */
    public function validCountries(array $countries): array
    {
        return array_values(array_filter(
            array_map('strval', $countries),
            fn (string $c): bool => in_array($c, self::COUNTRIES, true),
        ));
    }
}
