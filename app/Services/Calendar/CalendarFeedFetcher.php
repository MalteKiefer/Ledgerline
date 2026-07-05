<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Support\OutboundUrl;
use RuntimeException;

/**
 * Fetches a remote iCalendar feed for one-off imports and subscription
 * refreshes. SSRF-guarded (OutboundUrl), redirect-free, size- and time-capped.
 */
class CalendarFeedFetcher
{
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MiB

    public function fetch(string $url): string
    {
        // webcal:// is how calendar feeds are commonly linked; treat it as https.
        if (str_starts_with(strtolower($url), 'webcal://')) {
            $url = 'https://'.substr($url, 9);
        }
        try {
            // Resolves + pins the verified IP (SSRF/DNS-rebinding safe), no redirects.
            $response = OutboundUrl::client($url, 15)
                ->accept('text/calendar')
                ->get($url);
        } catch (\Throwable) {
            // Never surface the exception message — it embeds the (possibly
            // credential-bearing) URL into the response and the log.
            throw new RuntimeException('The calendar feed could not be reached.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('The feed responded with HTTP '.$response->status().'.');
        }
        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('The feed exceeds the maximum allowed size.');
        }
        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            throw new RuntimeException('The feed is not a valid iCalendar document.');
        }

        return $body;
    }
}
