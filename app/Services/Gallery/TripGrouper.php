<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use App\Services\Files\ReverseGeocoder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Groups geotagged photos into trips. Walking photos in capture order, a new
 * trip begins whenever the gap to the previous photo exceeds the day threshold
 * or the location jumps further than the radius (km). Each trip is labelled by
 * reverse-geocoding a representative photo and its date range.
 */
class TripGrouper
{
    public function __construct(private readonly ReverseGeocoder $geocoder) {}

    /**
     * @param  Collection<int, Photo>  $photos  Geotagged, capture-order.
     * @return list<array{key: int, label: ?string, from: Carbon, to: Carbon, photos: Collection<int, Photo>}>
     */
    public function group(Collection $photos, int $gapDays, float $radiusKm): array
    {
        $trips = [];
        $current = null;
        $previous = null;

        foreach ($photos as $photo) {
            $breaks = $previous !== null && (
                $previous->taken_at->diffInDays($photo->taken_at) > $gapDays
                || $this->distanceKm($previous->latitude, $previous->longitude, $photo->latitude, $photo->longitude) > $radiusKm
            );

            if ($current === null || $breaks) {
                if ($current !== null) {
                    $trips[] = $current;
                }
                $current = ['photos' => collect([$photo])];
            } else {
                $current['photos']->push($photo);
            }

            $previous = $photo;
        }

        if ($current !== null) {
            $trips[] = $current;
        }

        // Newest trips first; build label + date range.
        return collect($trips)
            ->map(function (array $trip): array {
                /** @var Collection<int, Photo> $photos */
                $photos = $trip['photos'];
                $first = $photos->first();

                return [
                    'key' => $first->id,
                    'label' => $this->label($first->latitude, $first->longitude),
                    'from' => $photos->min('taken_at'),
                    'to' => $photos->max('taken_at'),
                    'photos' => $photos,
                ];
            })
            ->sortByDesc('to')
            ->values()
            ->all();
    }

    /**
     * A short place label (city/town/region) for a coordinate, or null.
     */
    private function label(float $lat, float $lon): ?string
    {
        $address = $this->geocoder->lookup($lat, $lon);

        if ($address === null) {
            return null;
        }

        // Nominatim display_name is comma-separated, most specific first.
        $parts = array_map('trim', explode(',', $address));

        return $parts[0] ?? null;
    }

    /**
     * Great-circle distance between two coordinates, in kilometres.
     */
    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
