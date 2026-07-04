<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Services\Contacts\DavChangeLog;
use Illuminate\Support\Carbon;

/**
 * Materialises a subscribed remote ICS feed into a read-only calendar. Refresh is
 * a full replace (old objects removed, feed re-imported) so events deleted
 * upstream disappear locally; the change-log keeps CalDAV clients in sync.
 */
class SubscriptionRefresher
{
    public function __construct(
        private readonly CalendarFeedFetcher $fetcher,
        private readonly CalendarImporter $importer,
        private readonly DavChangeLog $changes,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function refresh(Calendar $calendar): array
    {
        $body = $this->fetcher->fetch((string) $calendar->subscription_url);

        // Full replace: drop existing objects (recording deletions for sync).
        foreach (CalendarObject::where('calendar_id', $calendar->id)->pluck('uri') as $uri) {
            $this->changes->recordCalendar($calendar, $uri, DavChangeOperation::Deleted);
        }
        CalendarObject::where('calendar_id', $calendar->id)->delete();

        $result = $this->importer->import($calendar, $body);
        $calendar->forceFill(['refreshed_at' => Carbon::now()])->save();

        return $result;
    }

    /** Whether the subscription is due for a refresh per its interval. */
    public function isDue(Calendar $calendar): bool
    {
        if ($calendar->subscription_url === null) {
            return false;
        }
        if ($calendar->refreshed_at === null) {
            return true;
        }
        $interval = max(15, (int) ($calendar->refresh_minutes ?: 60));

        return $calendar->refreshed_at->clone()->addMinutes($interval)->isPast();
    }
}
