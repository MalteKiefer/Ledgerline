<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\AppSettings;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\Contact;
use App\Services\Contacts\DavChangeLog;
use App\Services\Contacts\VCardService;
use Illuminate\Support\Str;

/**
 * Materialises read-only calendars derived from contacts: birthdays (BDAY) and
 * anniversaries (Apple-style itemN.X-ABDATE, a contact may have several). Each
 * date becomes a yearly, all-day recurring VEVENT. Rebuilt whenever a contact or
 * the settings change; contacts stay the single source of truth.
 */
class ContactDerivedCalendars
{
    public function __construct(
        private readonly VCardService $vcards,
        private readonly ICalService $ical,
        private readonly CalendarObjectPersister $persister,
        private readonly DavChangeLog $changes,
    ) {}

    /** Reconcile both derived calendars for every contact-owning user. */
    public function sync(): void
    {
        $settings = AppSettings::current();

        foreach (AddressBook::query()->distinct()->pluck('user_id') as $userId) {
            $this->reconcile((int) $userId, 'birthdays', (bool) $settings->calendar_birthdays_enabled);
            $this->reconcile((int) $userId, 'anniversaries', (bool) $settings->calendar_anniversaries_enabled);
        }
    }

    private function reconcile(int $userId, string $kind, bool $enabled): void
    {
        if (! $enabled) {
            Calendar::where('user_id', $userId)->where('uri', $kind)->get()->each->delete();

            return;
        }

        $calendar = Calendar::firstOrCreate(
            ['user_id' => $userId, 'uri' => $kind],
            [
                'name' => __('calendar.ui.'.$kind.'_calendar'),
                'color' => $kind === 'birthdays' ? '#e11d48' : '#7c3aed',
                'components' => ['VEVENT'],
                'synctoken' => 1,
                'read_only' => true,
            ],
        );

        $this->rebuild($calendar, $userId, $kind);
    }

    private function rebuild(Calendar $calendar, int $userId, string $kind): void
    {
        // Clear existing objects (record deletions so CalDAV clients re-sync).
        foreach (CalendarObject::where('calendar_id', $calendar->id)->pluck('uri') as $uri) {
            $this->changes->recordCalendar($calendar, $uri, DavChangeOperation::Deleted);
        }
        CalendarObject::where('calendar_id', $calendar->id)->delete();

        Contact::whereHas('addressBook', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->chunk(200, function ($contacts) use ($calendar, $kind): void {
                foreach ($contacts as $contact) {
                    foreach ($this->entriesFor($contact, $kind) as $entry) {
                        $ics = $this->ical->buildEvent([
                            'summary' => $entry['title'],
                            'start' => $entry['date'],
                            'all_day' => true,
                            'rrule' => 'FREQ=YEARLY',
                        ]);
                        $this->persister->persistNew($calendar, Str::uuid().'.ics', $ics);
                    }
                }
            });
    }

    /**
     * The dated entries a contact contributes to a derived calendar.
     *
     * @return list<array{date: string, title: string}>
     */
    private function entriesFor(Contact $contact, string $kind): array
    {
        $data = $this->vcards->parse($contact->vcard);
        $name = $data['fn'] ?: __('calendar.ui.unnamed_contact');

        if ($kind === 'birthdays') {
            $date = $this->normalizeDate($data['bday'] ?? null);

            return $date !== null ? [['date' => $date, 'title' => __('calendar.ui.birthday_of', ['name' => $name])]] : [];
        }

        $out = [];
        foreach ($data['anniversaries'] ?? [] as $ann) {
            $date = $this->normalizeDate($ann['date'] ?? null);
            if ($date === null) {
                continue;
            }
            $label = trim((string) ($ann['label'] ?? ''));
            $out[] = [
                'date' => $date,
                'title' => $label !== '' ? $name.' – '.$label : __('calendar.ui.anniversary_of', ['name' => $name]),
            ];
        }

        return $out;
    }

    /**
     * Normalise a vCard date (YYYY-MM-DD, YYYYMMDD, or --MMDD without a year) to a
     * Y-m-d start. A missing year uses 1970 — the yearly recurrence still fires on
     * the right month/day every year.
     */
    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);

        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', $value, $m) === 1) {
            return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^--(\d{2})-?(\d{2})/', $value, $m) === 1) {
            return sprintf('1970-%s-%s', $m[1], $m[2]);
        }
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
