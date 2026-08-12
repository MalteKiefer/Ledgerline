<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\GalleryPhoto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic gallery memory sources (no ML): "on this day" (same month/day
 * in past years) and auto-detected trips (contiguous runs of photos separated
 * by multi-day gaps, spanning several days). Owner-scoped queries are the
 * caller's responsibility — these operate on already-scoped GalleryPhoto rows.
 */
final class GalleryMemories
{
    /** A cluster only counts as a trip with at least this many photos… */
    private const TRIP_MIN_PHOTOS = 10;

    /** …spanning at least this many days (so a busy day at home is not a "trip"). */
    private const TRIP_MIN_SPAN_DAYS = 2;

    /** A gap larger than this (days) between consecutive photos starts a new cluster. */
    private const TRIP_GAP_DAYS = 3;

    /**
     * Photos taken on today's month/day in previous years, grouped by year
     * (newest first).
     *
     * @return list<array{year:int,years_ago:int,ids:list<int>}>
     */
    public function onThisDay(int $currentYear, int $month, int $day): array
    {
        $rows = GalleryPhoto::query()
            ->whereNotNull('taken_at')
            ->whereMonth('taken_at', $month)
            ->whereDay('taken_at', $day)
            ->whereYear('taken_at', '<', $currentYear)
            ->orderByDesc('taken_at')
            ->get(['id', 'taken_at']);

        /** @var array<int, list<int>> $byYear */
        $byYear = [];
        foreach ($rows as $p) {
            $y = (int) ($p->taken_at?->year ?? 0);
            if ($y === 0) {
                continue;
            }
            $byYear[$y][] = (int) $p->id;
        }
        krsort($byYear);

        $out = [];
        foreach ($byYear as $year => $ids) {
            $out[] = ['year' => $year, 'years_ago' => $currentYear - $year, 'ids' => $ids];
        }

        return $out;
    }

    /**
     * Auto-detected trips: cluster all dated photos by multi-day gaps, keep the
     * clusters that look like a trip (enough photos over enough days), newest
     * first, capped.
     *
     * @return list<array{from:string,to:string,place:?string,ids:list<int>}>
     */
    public function trips(int $limit = 8): array
    {
        $rows = GalleryPhoto::query()
            ->whereNotNull('taken_at')
            ->orderBy('taken_at')
            ->get(['id', 'taken_at', 'place']);

        $clusters = $this->cluster($rows);
        $trips = [];
        foreach ($clusters as $c) {
            /** @var Carbon $first */
            $first = $c['first'];
            /** @var Carbon $last */
            $last = $c['last'];
            if (count($c['ids']) < self::TRIP_MIN_PHOTOS) {
                continue;
            }
            if ($first->diffInDays($last) < self::TRIP_MIN_SPAN_DAYS) {
                continue;
            }
            $trips[] = [
                'from' => $first->toIso8601String(),
                'to' => $last->toIso8601String(),
                'place' => $this->commonPlace($c['places']),
                'ids' => $c['ids'],
            ];
        }
        // Newest first, capped.
        usort($trips, fn (array $a, array $b): int => strcmp($b['from'], $a['from']));

        return array_slice($trips, 0, $limit);
    }

    /**
     * @param  Collection<int, GalleryPhoto>  $rows
     * @return list<array{ids:list<int>,places:list<string>,first:Carbon,last:Carbon}>
     */
    private function cluster(Collection $rows): array
    {
        $clusters = [];
        $current = null;
        $prev = null;
        foreach ($rows as $p) {
            $t = $p->taken_at;
            if (! $t instanceof Carbon) {
                continue;
            }
            if ($current === null || ($prev instanceof Carbon && $prev->diffInDays($t) > self::TRIP_GAP_DAYS)) {
                if ($current !== null) {
                    $clusters[] = $current;
                }
                $current = ['ids' => [], 'places' => [], 'first' => $t, 'last' => $t];
            }
            $current['ids'][] = (int) $p->id;
            if (is_string($p->place) && $p->place !== '') {
                $current['places'][] = $p->place;
            }
            $current['last'] = $t;
            $prev = $t;
        }
        if ($current !== null) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    /** @param list<string> $places */
    private function commonPlace(array $places): ?string
    {
        if ($places === []) {
            return null;
        }
        $counts = array_count_values($places);
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
