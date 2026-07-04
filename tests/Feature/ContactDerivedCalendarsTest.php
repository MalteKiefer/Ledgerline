<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\AppSettings;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\User;
use App\Services\Calendar\ContactDerivedCalendars;
use App\Services\Contacts\ContactWriter;
use App\Services\Contacts\VCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactDerivedCalendarsTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $user): AddressBook
    {
        return AddressBook::create(['user_id' => $user->id, 'uri' => 'default', 'name' => 'Contacts', 'synctoken' => 1]);
    }

    public function test_vcard_round_trips_multiple_anniversaries(): void
    {
        $vcards = app(VCardService::class);
        $vcard = $vcards->build([
            'fn' => 'Jane', 'bday' => '1990-05-04',
            'anniversaries' => [
                ['date' => '2015-06-20', 'label' => 'Wedding'],
                ['date' => '2020-01-02', 'label' => 'Moved in'],
            ],
        ]);

        $parsed = $vcards->parse($vcard);
        $this->assertSame('1990-05-04', $parsed['bday']);
        $this->assertCount(2, $parsed['anniversaries']);
        $labels = array_column($parsed['anniversaries'], 'label');
        $this->assertContains('Wedding', $labels);
        $this->assertContains('Moved in', $labels);
    }

    public function test_enabling_birthdays_builds_a_calendar_from_contacts(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, ['fn' => 'Alice', 'bday' => '1988-03-15']);
        app(ContactWriter::class)->create($book, ['fn' => 'Bob']); // no birthday

        AppSettings::current()->update(['calendar_birthdays_enabled' => true]);
        app(ContactDerivedCalendars::class)->sync();

        $calendar = Calendar::where('user_id', $user->id)->where('uri', 'birthdays')->firstOrFail();
        $this->assertTrue($calendar->isReadOnly());
        $this->assertSame(1, CalendarObject::where('calendar_id', $calendar->id)->count());
        $object = CalendarObject::where('calendar_id', $calendar->id)->firstOrFail();
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $object->ics);
        $this->assertTrue($object->all_day);
    }

    public function test_enabling_anniversaries_builds_one_event_per_date(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, [
            'fn' => 'Carol',
            'anniversaries' => [
                ['date' => '2015-06-20', 'label' => 'Wedding'],
                ['date' => '2019-09-01', 'label' => 'Engagement'],
            ],
        ]);

        AppSettings::current()->update(['calendar_anniversaries_enabled' => true]);
        app(ContactDerivedCalendars::class)->sync();

        $calendar = Calendar::where('user_id', $user->id)->where('uri', 'anniversaries')->firstOrFail();
        $this->assertSame(2, CalendarObject::where('calendar_id', $calendar->id)->count());
        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $calendar->id, 'summary' => 'Carol – Wedding']);
    }

    public function test_disabling_removes_the_derived_calendar(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, ['fn' => 'Dan', 'bday' => '1970-01-01']);

        AppSettings::current()->update(['calendar_birthdays_enabled' => true]);
        app(ContactDerivedCalendars::class)->sync();
        $this->assertDatabaseHas('calendars', ['user_id' => $user->id, 'uri' => 'birthdays']);

        AppSettings::current()->update(['calendar_birthdays_enabled' => false]);
        app(ContactDerivedCalendars::class)->sync();
        $this->assertDatabaseMissing('calendars', ['user_id' => $user->id, 'uri' => 'birthdays']);
    }

    public function test_a_contact_change_rebuilds_the_calendar(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        AppSettings::current()->update(['calendar_birthdays_enabled' => true]);
        app(ContactDerivedCalendars::class)->sync(); // empty calendar exists

        // Adding a contact with a birthday fires the observer → rebuild.
        app(ContactWriter::class)->create($book, ['fn' => 'Eve', 'bday' => '2000-12-24']);

        $calendar = Calendar::where('user_id', $user->id)->where('uri', 'birthdays')->firstOrFail();
        $this->assertSame(1, CalendarObject::where('calendar_id', $calendar->id)->count());
    }

    public function test_a_birthday_without_a_year_still_recurs(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        // vCard 4.0 allows an omitted year: --MMDD.
        app(ContactWriter::class)->create($book, ['fn' => 'Frank', 'bday' => '--0704']);

        AppSettings::current()->update(['calendar_birthdays_enabled' => true]);
        app(ContactDerivedCalendars::class)->sync();

        $calendar = Calendar::where('user_id', $user->id)->where('uri', 'birthdays')->firstOrFail();
        $object = CalendarObject::where('calendar_id', $calendar->id)->firstOrFail();
        $this->assertSame('1970-07-04', $object->starts_at->format('Y-m-d'));
    }
}
