<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushJob;
use App\Models\AddressBook;
use App\Models\AppNotification;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * contacts:birthday-remind notifies (category=birthday) for a contact whose BDAY
 * matches today, once per contact per year.
 */
class RemindBirthdaysTest extends TestCase
{
    use RefreshDatabase;

    private function book(int $userId): AddressBook
    {
        return AddressBook::create([
            'user_id' => $userId, 'name' => 'Contacts', 'uri' => 'ab-'.$userId.'-'.Str::lower(Str::random(4)), 'synctoken' => 1,
        ]);
    }

    public function test_birthday_today_creates_one_notification_and_is_throttled(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $book = $this->book($user->id);
        $bday = Carbon::today()->format('Y-m-d'); // matches today, year-agnostic
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Jamie', 'bday' => $bday])->assertStatus(201);
        // The denormalised MM-DD column drives the match.
        $this->assertSame(Carbon::today()->format('m-d'), Contact::firstOrFail()->bday);

        $this->artisan('contacts:birthday-remind')->assertExitCode(0);

        $this->assertSame(1, AppNotification::where('category', 'birthday')->count());
        Queue::assertPushed(SendPushJob::class);

        $this->artisan('contacts:birthday-remind')->assertExitCode(0);
        $this->assertSame(1, AppNotification::where('category', 'birthday')->count());
    }

    public function test_contact_without_birthday_today_is_skipped(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $book = $this->book($user->id);
        $other = Carbon::today()->addDays(5)->format('Y-m-d');
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Later', 'bday' => $other])->assertStatus(201);

        $this->artisan('contacts:birthday-remind')->assertExitCode(0);

        $this->assertSame(0, AppNotification::where('category', 'birthday')->count());
    }
}
