<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function calendar(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId,
            'name' => 'Personal',
            'uri' => 'calendar-'.$userId.'-'.Str::lower(Str::random(4)),
            'color' => '#6750a4',
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);
    }

    private function event(Calendar $calendar, array $attrs = []): CalendarEvent
    {
        return CalendarEvent::create(array_merge([
            'calendar_id' => $calendar->id,
            'uri' => Str::uuid()->toString().'.ics',
            'etag' => Str::random(16),
            'uid' => Str::uuid()->toString(),
            'component' => 'VEVENT',
            'ics' => "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'summary' => 'Concert',
            'dtstart' => '2026-06-01 18:00:00',
            'dtend' => '2026-06-01 21:00:00',
            'all_day' => false,
            'sequence' => 0,
        ], $attrs));
    }

    private function photo(int $userId, string $takenAt, array $attrs = []): GalleryPhoto
    {
        $photo = new GalleryPhoto;
        $photo->forceFill(array_merge([
            'user_id' => $userId,
            'storage_path' => 'gallery/'.Str::uuid()->toString(),
            'name' => 'IMG.jpg',
            'mime' => 'image/jpeg',
            'media_type' => 'image',
            'status' => 'ready',
            'size' => 1024,
            'taken_at' => $takenAt,
        ], $attrs))->save();

        return $photo;
    }

    public function test_photos_taken_during_the_event_are_returned_and_others_are_not(): void
    {
        $user = $this->signIn();
        $event = $this->event($this->calendar($user->id));

        $during = $this->photo($user->id, '2026-06-01 19:30:00');
        $before = $this->photo($user->id, '2026-06-01 17:59:00');
        $after = $this->photo($user->id, '2026-06-01 21:00:01');

        $res = $this->getJson(route('calendar.events.photos', ['event' => $event->id]))
            ->assertOk()
            ->assertJsonPath('matched_by', 'time')
            ->assertJsonPath('radius_m', null)
            ->assertJsonCount(1, 'photos');

        $this->assertSame($during->id, $res->json('photos.0.id'));
        foreach ([$before, $after] as $outside) {
            $this->assertNotContains($outside->id, $res->json('photos.*.id'));
        }
    }

    public function test_all_day_end_is_exclusive(): void
    {
        $user = $this->signIn();
        // 1.–2. June as an all-day event: DTEND 3 June is exclusive, so the last
        // day covered is the 2nd.
        $event = $this->event($this->calendar($user->id), [
            'dtstart' => '2026-06-01 00:00:00',
            'dtend' => '2026-06-03 00:00:00',
            'all_day' => true,
        ]);

        $inside = $this->photo($user->id, '2026-06-02 23:59:00');
        $onEndDay = $this->photo($user->id, '2026-06-03 09:00:00');

        $res = $this->getJson(route('calendar.events.photos', ['event' => $event->id]))->assertOk();

        $this->assertSame([$inside->id], $res->json('photos.*.id'));
        $this->assertNotContains($onEndDay->id, $res->json('photos.*.id'));
    }

    public function test_an_event_without_an_end_assumes_one_hour(): void
    {
        $user = $this->signIn();
        $event = $this->event($this->calendar($user->id), ['dtstart' => '2026-06-01 18:00:00', 'dtend' => null]);

        $inside = $this->photo($user->id, '2026-06-01 18:45:00');
        $outside = $this->photo($user->id, '2026-06-01 19:30:00');

        $res = $this->getJson(route('calendar.events.photos', ['event' => $event->id]))->assertOk();

        $this->assertSame([$inside->id], $res->json('photos.*.id'));
        $this->assertNotContains($outside->id, $res->json('photos.*.id'));
    }

    public function test_event_coordinates_exclude_a_distant_photo_and_report_place_matching(): void
    {
        $user = $this->signIn();
        $event = $this->event($this->calendar($user->id), [
            'geo_lat' => 48.137264,
            'geo_lon' => 11.574978,
        ]);

        // ~100 m away — same venue.
        $near = $this->photo($user->id, '2026-06-01 19:00:00', ['lat' => 48.138164, 'lng' => 11.574978]);
        // Same hour, different city.
        $far = $this->photo($user->id, '2026-06-01 19:05:00', ['lat' => 52.520008, 'lng' => 13.404954]);
        // Right time, but no coordinates — cannot be placed at the venue.
        $noCoords = $this->photo($user->id, '2026-06-01 19:10:00');

        $res = $this->getJson(route('calendar.events.photos', ['event' => $event->id]))
            ->assertOk()
            ->assertJsonPath('matched_by', 'time_and_place')
            ->assertJsonPath('radius_m', 500);

        $this->assertSame([$near->id], $res->json('photos.*.id'));
        foreach ([$far, $noCoords] as $excluded) {
            $this->assertNotContains($excluded->id, $res->json('photos.*.id'));
        }
    }

    public function test_without_event_coordinates_time_alone_decides(): void
    {
        $user = $this->signIn();
        $event = $this->event($this->calendar($user->id));
        $far = $this->photo($user->id, '2026-06-01 19:05:00', ['lat' => 52.520008, 'lng' => 13.404954]);

        $this->getJson(route('calendar.events.photos', ['event' => $event->id]))
            ->assertOk()
            ->assertJsonPath('matched_by', 'time')
            ->assertJsonPath('photos.0.id', $far->id);
    }

    public function test_a_foreign_calendar_is_not_confirmed_to_exist(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($this->calendar($owner->id));
        $this->photo($owner->id, '2026-06-01 19:00:00');

        $this->signIn();
        $this->getJson(route('calendar.events.photos', ['event' => $event->id]))->assertNotFound();
    }

    public function test_archived_and_trashed_photos_are_omitted(): void
    {
        $user = $this->signIn();
        $event = $this->event($this->calendar($user->id));

        $visible = $this->photo($user->id, '2026-06-01 19:00:00');
        $this->photo($user->id, '2026-06-01 19:01:00', ['archived_at' => '2026-06-02 00:00:00']);
        $this->photo($user->id, '2026-06-01 19:02:00')->delete();

        $this->getJson(route('calendar.events.photos', ['event' => $event->id]))
            ->assertOk()
            ->assertJsonCount(1, 'photos')
            ->assertJsonPath('photos.0.id', $visible->id);
    }
}
