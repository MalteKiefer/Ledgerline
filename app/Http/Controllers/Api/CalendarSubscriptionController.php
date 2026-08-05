<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fetch a PUBLIC iCalendar (.ics) feed the user subscribed to, so the client can
 * parse + overlay it read-only. The subscription list (URLs) lives client-side in
 * the sealed calendar store; the client posts one URL here to fetch it. This is a
 * user-initiated, SSRF-guarded outbound fetch of PUBLIC data (holidays, sports,
 * etc.) — same class as geocode/maps-resolve. Nothing is persisted or logged; the
 * body is capped and must look like iCalendar. See the security register.
 */
class CalendarSubscriptionController extends Controller
{
    private const MAX_BYTES = 2_000_000;

    public function fetch(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $url = $request->string('url')->value();

        // Normalise webcal:// (common for calendar subscriptions) to https://.
        if (str_starts_with(strtolower($url), 'webcal://')) {
            $url = 'https://'.substr($url, 9);
        }

        if (! OutboundUrl::safe($url)) {
            return response()->json(['error' => 'unsafe_url'], 422)->header('Cache-Control', 'no-store');
        }

        try {
            $res = OutboundUrl::client($url, 15, self::MAX_BYTES)->get($url);
        } catch (\Throwable) {
            return response()->json(['error' => 'fetch_failed'], 502)->header('Cache-Control', 'no-store');
        }

        if (! $res->ok()) {
            return response()->json(['error' => 'fetch_failed'], 502)->header('Cache-Control', 'no-store');
        }

        $body = $res->body();
        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            return response()->json(['error' => 'not_ical'], 422)->header('Cache-Control', 'no-store');
        }

        return response()->json(['ics' => mb_substr($body, 0, self::MAX_BYTES)])
            ->header('Cache-Control', 'no-store');
    }
}
