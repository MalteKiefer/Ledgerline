<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Services\Calendar\CalendarObjectPersister as Persister;
use Illuminate\Support\Carbon;

/**
 * Materialises a subscribed remote ICS feed into a read-only calendar. Refresh
 * reconciles by diff (add new, update changed, delete vanished) against stable
 * per-event uris, so events removed upstream disappear locally while unchanged
 * events keep their identity and the CalDAV sync token stays put on a no-op.
 */
class SubscriptionRefresher
{
    public function __construct(
        private readonly CalendarFeedFetcher $fetcher,
        private readonly ICalService $ical,
        private readonly Persister $persister,
    ) {}

    /**
     * @return array{count: int}
     */
    public function refresh(Calendar $calendar): array
    {
        $body = $this->fetcher->fetch((string) $calendar->subscription_url);
        $map = $this->ical->eventMap($body);

        $this->persister->replace($calendar, $map);
        $calendar->forceFill(['refreshed_at' => Carbon::now()])->save();

        return ['count' => count($map)];
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
