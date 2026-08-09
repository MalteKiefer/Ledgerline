<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\User;
use App\Services\Calendar\SpecialCalendarGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarSpecialTest extends TestCase
{
    use RefreshDatabase;

    private function book(int $userId, string $name = 'Book'): AddressBook
    {
        return AddressBook::create([
            'user_id' => $userId,
            'name' => $name,
            'uri' => 'book-'.$userId.'-'.Str::lower(Str::random(4)),
            'synctoken' => 1,
        ]);
    }

    private function contact(AddressBook $book, string $fn, string $bday): Contact
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:4.0\r\nUID:".Str::uuid()."\r\nFN:{$fn}\r\nBDAY:{$bday}\r\nEND:VCARD\r\n";

        return Contact::create([
            'address_book_id' => $book->id,
            'uri' => Str::uuid().'.vcf',
            'etag' => md5($vcard),
            'uid' => (string) Str::uuid(),
            'vcard' => $vcard,
            'fn' => $fn,
        ]);
    }

    public function test_birthdays_generate_a_yearly_recurring_event_per_contact(): void
    {
        $user = $this->signIn();
        $this->contact($this->book($user->id), 'Ada Lovelace', '19901215');

        $id = $this->postJson(route('calendars.special'), [
            'name' => 'Birthdays', 'kind' => 'birthdays', 'color' => '#ff8800',
        ])->assertStatus(201)->assertJsonPath('created', 1)->json('id');

        $event = CalendarEvent::where('calendar_id', $id)->firstOrFail();
        $this->assertStringContainsString('Ada Lovelace', (string) $event->summary);
        $this->assertTrue($event->all_day);
        $this->assertStringContainsString('FREQ=YEARLY', (string) $event->rrule);
        $this->assertSame(12, $event->dtstart?->month);
        $this->assertSame(15, $event->dtstart?->day);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $event->ics);
        // The calendar is marked special (generated + read-only).
        $this->assertTrue(Calendar::findOrFail($id)->isSpecial());
    }

    public function test_birthday_without_a_year_uses_a_neutral_year(): void
    {
        $user = $this->signIn();
        $this->contact($this->book($user->id), 'No Year', '--0229');

        $id = $this->postJson(route('calendars.special'), ['name' => 'B', 'kind' => 'birthdays'])
            ->assertStatus(201)->json('id');

        $event = CalendarEvent::where('calendar_id', $id)->firstOrFail();
        $this->assertSame(2, $event->dtstart?->month);
        $this->assertSame(29, $event->dtstart?->day);
    }

    public function test_holidays_generation_covers_the_year_range(): void
    {
        $this->signIn();

        $created = $this->postJson(route('calendars.special'), ['name' => 'Feiertage', 'kind' => 'holidays'])
            ->assertStatus(201)->json('created');

        // 9 national holidays × 5 years (currentYear-1 .. currentYear+3).
        $this->assertSame(45, $created);
        $this->assertDatabaseHas('calendar_events', ['summary' => 'Neujahr', 'all_day' => true]);
        $this->assertDatabaseHas('calendar_events', ['summary' => 'Ostermontag']);
        $this->assertDatabaseHas('calendar_events', ['summary' => '1. Weihnachtstag']);
    }

    public function test_german_holidays_have_correct_dates(): void
    {
        $gen = app(SpecialCalendarGenerator::class);

        $this->assertSame('2024-03-31', $gen->easterSunday(2024)->format('Y-m-d'));

        $byName = [];
        foreach ($gen->germanHolidays(2024) as $h) {
            $byName[$h['name']] = $h['date'];
        }
        $this->assertSame('2024-01-01', $byName['Neujahr']);
        $this->assertSame('2024-03-29', $byName['Karfreitag']);
        $this->assertSame('2024-04-01', $byName['Ostermontag']);
        $this->assertSame('2024-05-09', $byName['Christi Himmelfahrt']);
        $this->assertSame('2024-05-20', $byName['Pfingstmontag']);
        $this->assertSame('2024-10-03', $byName['Tag der Deutschen Einheit']);
        $this->assertSame('2024-12-26', $byName['2. Weihnachtstag']);
    }

    public function test_birthdays_are_owner_scoped_to_the_acting_user(): void
    {
        $owner = $this->signIn();
        $this->contact($this->book($owner->id), 'Mine Birthday', '19800101');
        // A foreign user's contact must never be generated into the owner's calendar.
        $other = User::factory()->create();
        $this->contact($this->book($other->id), 'Foreign Person', '19700202');

        $id = $this->postJson(route('calendars.special'), ['name' => 'B', 'kind' => 'birthdays'])
            ->assertStatus(201)->assertJsonPath('created', 1)->json('id');

        $this->assertSame(1, CalendarEvent::where('calendar_id', $id)->count());
        $this->assertDatabaseMissing('calendar_events', ['summary' => 'Birthday: Foreign Person']);
    }

    public function test_regenerate_rebuilds_from_current_contacts(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->contact($book, 'First', '19900101');

        $id = $this->postJson(route('calendars.special'), ['name' => 'B', 'kind' => 'birthdays'])
            ->assertStatus(201)->json('id');
        $this->assertSame(1, CalendarEvent::where('calendar_id', $id)->count());

        // Add a second contact, then regenerate → both present, no duplicates.
        $this->contact($book, 'Second', '19910202');
        $this->postJson(route('calendars.regenerate', $id))
            ->assertOk()->assertJsonPath('created', 2);
        $this->assertSame(2, CalendarEvent::where('calendar_id', $id)->count());
    }

    public function test_special_calendars_reject_manual_events_and_normal_calendars_cannot_regenerate(): void
    {
        $this->signIn();

        $specialId = $this->postJson(route('calendars.special'), ['name' => 'H', 'kind' => 'holidays'])
            ->assertStatus(201)->json('id');

        // No manual event may target a generated calendar.
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $specialId, 'summary' => 'x', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertStatus(422);

        // A normal calendar has nothing to regenerate.
        $normalId = $this->postJson(route('calendars.store'), ['name' => 'Normal'])->assertStatus(201)->json('id');
        $this->postJson(route('calendars.regenerate', $normalId))->assertStatus(422);
    }

    public function test_regenerating_a_foreign_calendar_is_hidden_by_owner_scope(): void
    {
        $other = User::factory()->create();
        $foreign = Calendar::create([
            'user_id' => $other->id,
            'name' => 'Theirs',
            'uri' => 'theirs-'.Str::lower(Str::random(4)),
            'kind' => 'holidays',
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);

        $this->signIn();
        $this->postJson(route('calendars.regenerate', $foreign->id))->assertNotFound();
    }
}
