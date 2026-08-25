<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\GalleryPhoto;
use Carbon\CarbonImmutable;

/**
 * Joins two modules that share no relation: which of the caller's photos were
 * taken while a calendar event was running. Nothing new is recorded — the match
 * is derived from columns both sides already have (calendar_events.dtstart/
 * dtend/geo_lat/geo_lon vs gallery_photos.taken_at/lat/lng).
 *
 * Owner-scoping is the caller's responsibility for the event; the photo query
 * relies on the OwnsUserData global scope, so it always returns the photos of
 * the *authenticated* user.
 */
final class EventPhotos
{
    /** Hard cap on returned rows — a multi-day event can overlap a whole trip. */
    private const MAX_PHOTOS = 200;

    /**
     * A timed event without DTEND has, per RFC 5545, zero duration — taken
     * literally nothing could ever match. An hour is the length the editor
     * itself suggests for a new appointment, so it is the least surprising
     * stand-in.
     */
    private const DEFAULT_DURATION_MINUTES = 60;

    /**
     * Distance (metres) a photo may be from the event's coordinate. An event's
     * coordinate is a geocoded building centroid and consumer EXIF GPS is
     * accurate to tens of metres, so anything much tighter would drop genuine
     * photos taken on the far side of the venue; 500 m still excludes a
     * different part of town.
     */
    private const RADIUS_M = 500.0;

    /** Mean earth radius (metres) for the haversine distance. */
    private const EARTH_RADIUS_M = 6_371_000.0;

    /**
     * @return array{matched_by:'time'|'time_and_place',radius_m:?int,from:?string,to:?string,photos:list<array<string,mixed>>}
     */
    public function forEvent(CalendarEvent $event): array
    {
        $from = $event->dtstart;
        if (! $from instanceof CarbonImmutable) {
            return ['matched_by' => 'time', 'radius_m' => null, 'from' => null, 'to' => null, 'photos' => []];
        }
        $to = $event->dtend instanceof CarbonImmutable
            ? $event->dtend
            : ($event->all_day ? $from->addDay() : $from->addMinutes(self::DEFAULT_DURATION_MINUTES));

        $lat = $event->geo_lat;
        $lon = $event->geo_lon;
        $withPlace = $lat !== null && $lon !== null;

        $query = GalleryPhoto::query()
            ->whereNull('archived_at')
            ->whereNotNull('taken_at')
            ->where('taken_at', '>=', $from);

        // An all-day DTEND is exclusive (RFC 5545): the last day of a 25.–27.
        // event is the 26th, so a photo taken on the 27th is not part of it. A
        // timed end is a real instant and the photo taken exactly then belongs
        // to the event.
        $event->all_day
            ? $query->where('taken_at', '<', $to)
            : $query->where('taken_at', '<=', $to);

        if ($withPlace) {
            // Cheap bounding box in SQL, exact haversine below. A photo without
            // coordinates cannot be placed at the venue, so it is left out
            // rather than reported under a "time_and_place" match it did not
            // pass.
            $dLat = self::RADIUS_M / 111_320.0;
            $cos = max(0.01, cos(deg2rad($lat)));
            $dLon = $dLat / $cos;
            $query->whereNotNull('lat')->whereNotNull('lng')
                ->whereBetween('lat', [$lat - $dLat, $lat + $dLat])
                ->whereBetween('lng', [$lon - $dLon, $lon + $dLon]);
        }

        // The box circumscribes the circle (≈27 % more area), so double the cap
        // leaves enough headroom that the haversine filter cannot truncate a
        // full page.
        $rows = $query->orderBy('taken_at')
            ->limit($withPlace ? self::MAX_PHOTOS * 2 : self::MAX_PHOTOS)
            ->get();

        $photos = [];
        foreach ($rows as $photo) {
            if ($withPlace) {
                $pLat = $photo->lat;
                $pLng = $photo->lng;
                if ($pLat === null || $pLng === null || $this->distance($lat, $lon, $pLat, $pLng) > self::RADIUS_M) {
                    continue;
                }
            }
            $photos[] = [
                'id' => $photo->id,
                'name' => $photo->name,
                'media_type' => $photo->media_type,
                'thumb' => (bool) $photo->thumb_ready,
                'taken_at' => $photo->taken_at?->toIso8601String(),
                'place' => $photo->place,
                'lat' => $photo->lat,
                'lng' => $photo->lng,
            ];
            if (count($photos) >= self::MAX_PHOTOS) {
                break;
            }
        }

        return [
            // The caller cannot tell a confident from a merely plausible hit
            // without this: without event coordinates the time window alone
            // decides, and two things happening in the same hour elsewhere look
            // identical to being there.
            'matched_by' => $withPlace ? 'time_and_place' : 'time',
            'radius_m' => $withPlace ? (int) self::RADIUS_M : null,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'photos' => $photos,
        ];
    }

    /** Great-circle distance in metres. */
    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
