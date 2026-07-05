<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Calendar;
use App\Models\PublicShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_a_public_calendar_link_openable_without_auth(): void
    {
        $alice = User::factory()->create();
        $calendar = Calendar::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Team', 'components' => ['VEVENT'], 'synctoken' => 1]);

        $url = $this->actingAs($alice)->postJson(route('public-share.store'), ['type' => 'calendars', 'id' => $calendar->id])
            ->assertCreated()->json('url');

        $token = PublicShare::firstOrFail()->token;

        // No auth needed for the public views.
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertSee('Team');
        $this->get(route('public-share.ics', $token))->assertOk()->assertHeader('content-type', 'text/calendar; charset=utf-8');
    }

    public function test_owner_creates_a_public_address_book_link(): void
    {
        $alice = User::factory()->create();
        $book = AddressBook::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Shared', 'synctoken' => 1]);

        $this->actingAs($alice)->postJson(route('public-share.store'), ['type' => 'address-books', 'id' => $book->id])->assertCreated();
        $share = PublicShare::firstOrFail();

        $this->app['auth']->forgetGuards();
        $this->get(route('public-share.show', $share->token))->assertOk();
        $this->get(route('public-share.vcf', $share->token))->assertOk()->assertHeader('content-type', 'text/vcard; charset=utf-8');
    }

    public function test_a_read_only_calendar_cannot_get_a_public_link(): void
    {
        $alice = User::factory()->create();
        $holidays = Calendar::create(['user_id' => $alice->id, 'uri' => 'holidays', 'name' => 'Holidays', 'components' => ['VEVENT'], 'synctoken' => 1, 'read_only' => true]);

        $this->actingAs($alice)->postJson(route('public-share.store'), ['type' => 'calendars', 'id' => $holidays->id])->assertStatus(422);
    }

    public function test_only_owner_can_revoke(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $calendar = Calendar::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Team', 'components' => ['VEVENT'], 'synctoken' => 1]);
        $this->actingAs($alice)->postJson(route('public-share.store'), ['type' => 'calendars', 'id' => $calendar->id]);
        $share = PublicShare::firstOrFail();

        $this->actingAs($bob)->deleteJson(route('public-share.destroy', $share->id))->assertForbidden();
        $this->actingAs($alice)->deleteJson(route('public-share.destroy', $share->id))->assertOk();
        $this->assertSame(0, PublicShare::count());
    }
}
