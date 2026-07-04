<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\User;
use App\Services\Calendar\SubscriptionRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:feed-1\r\nSUMMARY:Public Holiday\r\nDTSTART:20260901T100000Z\r\nDTEND:20260901T110000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private function calendarFor(User $user): Calendar
    {
        return Calendar::create([
            'user_id' => $user->id, 'uri' => 'default', 'name' => 'Calendar',
            'color' => '#3366cc', 'components' => ['VEVENT'], 'synctoken' => 1,
        ]);
    }

    public function test_it_imports_events_from_a_public_url(): void
    {
        Http::fake(['https://example.com/*' => Http::response(self::FEED, 200)]);
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $this->postJson(route('calendar.import-url'), [
            'url' => 'https://example.com/feed.ics', 'calendar_id' => $calendar->id,
        ])->assertOk()->assertJson(['created' => 1]);

        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $calendar->id, 'summary' => 'Public Holiday']);
    }

    public function test_it_rejects_an_unsafe_feed_url(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        // Link-local (cloud metadata) is always refused by the SSRF guard.
        $this->postJson(route('calendar.import-url'), [
            'url' => 'http://169.254.169.254/latest/meta-data/', 'calendar_id' => $calendar->id,
        ])->assertStatus(422);
        $this->assertSame(0, CalendarObject::count());
    }

    public function test_it_subscribes_to_a_feed_as_a_read_only_calendar(): void
    {
        Http::fake(['https://example.com/*' => Http::response(self::FEED, 200)]);
        $this->signIn();

        $id = $this->postJson(route('calendar.subscribe'), [
            'url' => 'https://example.com/feed.ics', 'name' => 'Holidays', 'refresh_minutes' => 60,
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('calendars', ['id' => $id, 'name' => 'Holidays', 'read_only' => true]);
        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $id, 'summary' => 'Public Holiday']);
    }

    public function test_a_failing_subscription_is_not_persisted(): void
    {
        Http::fake(['https://example.com/*' => Http::response('nope', 500)]);
        $this->signIn();

        $this->postJson(route('calendar.subscribe'), [
            'url' => 'https://example.com/feed.ics', 'name' => 'Broken',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('calendars', ['name' => 'Broken']);
    }

    public function test_refresh_replaces_events_and_prunes_removed_ones(): void
    {
        Http::fake(['https://example.com/*' => Http::response(self::FEED, 200)]);
        $user = $this->signIn();
        $calendar = Calendar::create([
            'user_id' => $user->id, 'uri' => 'feed', 'name' => 'Sub', 'components' => ['VEVENT'],
            'synctoken' => 1, 'read_only' => true, 'subscription_url' => 'https://example.com/feed.ics',
            'refresh_minutes' => 60, 'refreshed_at' => Carbon::now()->subDay(),
        ]);
        // A stale local event that is no longer in the feed.
        CalendarObject::create([
            'calendar_id' => $calendar->id, 'uri' => 'old.ics', 'etag' => 'x', 'component' => 'VEVENT',
            'ics' => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:old\r\nSUMMARY:Old\r\nDTSTART:20260101T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'summary' => 'Old',
        ]);

        app(SubscriptionRefresher::class)->refresh($calendar);

        $this->assertDatabaseMissing('calendar_objects', ['calendar_id' => $calendar->id, 'summary' => 'Old']);
        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $calendar->id, 'summary' => 'Public Holiday']);
        $this->assertNotNull($calendar->fresh()->refreshed_at);
    }

    public function test_the_refresh_command_only_touches_due_feeds(): void
    {
        Http::fake(['https://example.com/*' => Http::response(self::FEED, 200)]);
        $user = User::factory()->create();
        $due = Calendar::create([
            'user_id' => $user->id, 'uri' => 'due', 'name' => 'Due', 'components' => ['VEVENT'], 'synctoken' => 1,
            'read_only' => true, 'subscription_url' => 'https://example.com/a.ics', 'refresh_minutes' => 60,
            'refreshed_at' => Carbon::now()->subHours(3),
        ]);
        $fresh = Calendar::create([
            'user_id' => $user->id, 'uri' => 'fresh', 'name' => 'Fresh', 'components' => ['VEVENT'], 'synctoken' => 1,
            'read_only' => true, 'subscription_url' => 'https://example.com/b.ics', 'refresh_minutes' => 60,
            'refreshed_at' => Carbon::now(),
        ]);

        $this->artisan('calendar:refresh-subscriptions')->assertSuccessful();

        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $due->id]);
        $this->assertSame(0, CalendarObject::where('calendar_id', $fresh->id)->count());
    }
}
