<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_and_returns_an_ical_feed(): void
    {
        Http::fake([
            'example.com/*' => Http::response("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Hi\r\nDTSTART:20260805T090000\r\nEND:VEVENT\r\nEND:VCALENDAR", 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('calendar.ics-fetch', ['url' => 'https://example.com/cal.ics']))
            ->assertOk()
            ->assertJsonPath('ics', fn ($ics) => str_contains((string) $ics, 'BEGIN:VCALENDAR'));
    }

    public function test_rejects_a_non_ical_response(): void
    {
        Http::fake(['example.com/*' => Http::response('<html>nope</html>', 200)]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('calendar.ics-fetch', ['url' => 'https://example.com/x']))
            ->assertStatus(422)
            ->assertJson(['error' => 'not_ical']);
    }

    public function test_refuses_a_link_local_url_without_fetching(): void
    {
        Http::fake(); // any real fetch would fail the test

        $this->actingAs(User::factory()->create())
            ->getJson(route('calendar.ics-fetch', ['url' => 'http://169.254.169.254/latest/meta-data']))
            ->assertStatus(422)
            ->assertJson(['error' => 'unsafe_url']);

        Http::assertNothingSent();
    }
}
