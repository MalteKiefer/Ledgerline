<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Services\Gallery\TripGrouper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripGrouperTest extends TestCase
{
    use RefreshDatabase;

    private function photo(string $takenAt, float $lat, float $lon): Photo
    {
        return Photo::factory()->make(['taken_at' => $takenAt, 'latitude' => $lat, 'longitude' => $lon]);
    }

    public function test_it_splits_trips_by_time_gap_and_distance(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Las Vegas, Nevada'], 200)]);

        $photos = collect([
            // Trip 1: Las Vegas, consecutive days.
            $this->photo('2026-03-01 10:00:00', 36.1699, -115.1398),
            $this->photo('2026-03-02 11:00:00', 36.1700, -115.1400),
            // Trip 2: same place but 10 days later (time gap).
            $this->photo('2026-03-12 09:00:00', 36.1699, -115.1398),
            // Trip 3: far away, next day (distance jump).
            $this->photo('2026-03-13 09:00:00', 48.1372, 11.5756),
        ]);

        $trips = app(TripGrouper::class)->group($photos, gapDays: 2, radiusKm: 100);

        $this->assertCount(3, $trips);
        // Newest trip first.
        $this->assertSame('2026-03-13', $trips[0]['to']->toDateString());
    }

    public function test_close_photos_over_consecutive_days_stay_one_trip(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Lohmar'], 200)]);

        $photos = collect([
            $this->photo('2026-05-01 10:00:00', 50.90, 7.20),
            $this->photo('2026-05-02 10:00:00', 50.91, 7.21),
            $this->photo('2026-05-03 10:00:00', 50.90, 7.19),
        ]);

        $trips = app(TripGrouper::class)->group($photos, gapDays: 2, radiusKm: 100);

        $this->assertCount(1, $trips);
        $this->assertSame(3, $trips[0]['photos']->count());
        $this->assertSame('Lohmar', $trips[0]['label']);
    }

    public function test_trips_page_renders(): void
    {
        $this->signIn();
        $this->get(route('gallery.trips'))->assertOk();
    }
}
