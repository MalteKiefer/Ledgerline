<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Calendar;
use App\Models\ResourceShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSharingTest extends TestCase
{
    use RefreshDatabase;

    /** A still-shareable resource (calendar) owned by the given user. */
    private function calendarOf(User $u, string $name): Calendar
    {
        $this->actingAs($u);
        $calendar = Calendar::create(['name' => $name, 'uri' => 'default', 'components' => ['VEVENT'], 'synctoken' => 1]);
        $this->app['auth']->forgetGuards();

        return $calendar;
    }

    public function test_a_third_user_still_cannot_see_the_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $calendar = $this->calendarOf($alice, 'Private');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'calendars', 'id' => $calendar->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();

        $this->actingAs($carol);
        $this->assertNull(Calendar::find($calendar->id));
    }

    public function test_only_the_owner_can_share_a_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $calendar = $this->calendarOf($alice, 'Mine');

        // Bob (not the owner) cannot share Alice's calendar.
        $this->actingAs($bob)->postJson(route('shares.store'), [
            'type' => 'calendars', 'id' => $calendar->id, 'email' => $alice->email, 'permission' => 'read',
        ])->assertForbidden();
    }

    public function test_owner_can_share_a_calendar_and_address_book(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $calendar = Calendar::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Team', 'components' => ['VEVENT'], 'synctoken' => 1]);
        $book = AddressBook::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Shared', 'synctoken' => 1]);

        $this->actingAs($alice)->postJson(route('shares.store'), ['type' => 'calendars', 'id' => $calendar->id, 'email' => $bob->email, 'permission' => 'write'])->assertCreated();
        $this->actingAs($alice)->postJson(route('shares.store'), ['type' => 'address-books', 'id' => $book->id, 'email' => $bob->email, 'permission' => 'read'])->assertCreated();

        // Bob now sees both via the owned-or-shared scope.
        $this->actingAs($bob);
        $this->assertNotNull(Calendar::find($calendar->id));
        $this->assertNotNull(AddressBook::find($book->id));
    }

    public function test_a_read_only_calendar_cannot_be_shared(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $holidays = Calendar::create(['user_id' => $alice->id, 'uri' => 'holidays', 'name' => 'Holidays', 'components' => ['VEVENT'], 'synctoken' => 1, 'read_only' => true]);

        $this->actingAs($alice)->postJson(route('shares.store'), ['type' => 'calendars', 'id' => $holidays->id, 'email' => $bob->email, 'permission' => 'read'])
            ->assertStatus(422);
    }

    public function test_internal_share_notifies_the_recipient_in_app(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $calendar = $this->calendarOf($alice, 'Plan');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'calendars', 'id' => $calendar->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated()->assertJsonStructure(['ok', 'id', 'link']);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $bob->id, 'category' => 'share']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $alice->id, 'category' => 'share']);
    }

    public function test_share_email_is_rejected_without_a_mail_server(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $calendar = $this->calendarOf($alice, 'Plan');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'calendars', 'id' => $calendar->id, 'email' => $bob->email, 'permission' => 'read',
        ]);
        $share = ResourceShare::firstOrFail();

        // No SMTP configured → the mail-share option is refused (offer copy link instead).
        $this->actingAs($alice)->postJson(route('shares.email', $share->id))->assertStatus(422);

        // A non-owner cannot trigger it either.
        $this->actingAs($bob)->postJson(route('shares.email', $share->id))->assertForbidden();
    }

    public function test_owner_can_revoke_a_share(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $calendar = $this->calendarOf($alice, 'Temp');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'calendars', 'id' => $calendar->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();
        $share = ResourceShare::firstOrFail();

        // Bob can see it while the share exists.
        $this->actingAs($bob);
        $this->assertNotNull(Calendar::find($calendar->id));

        $this->actingAs($alice)->deleteJson(route('shares.destroy', $share->id))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs($bob);
        $this->assertNull(Calendar::find($calendar->id));
    }
}
