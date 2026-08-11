<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\AddressBookShare;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contacts sharing (viewer-only address-book grants) + the subscribeable
 * birthday .ics feed.
 */
class ContactShareTest extends TestCase
{
    use RefreshDatabase;

    private function book(int $userId, string $name = 'Contacts'): AddressBook
    {
        return AddressBook::create(['user_id' => $userId, 'name' => $name, 'uri' => 'ab-'.$userId.'-'.Str::lower(Str::random(4)), 'synctoken' => 1]);
    }

    private function contact(AddressBook $book, string $fn, ?string $bday = null): Contact
    {
        $c = new Contact;
        $c->forceFill([
            'address_book_id' => $book->id, 'uid' => (string) Str::uuid(), 'uri' => Str::random(8).'.vcf',
            'etag' => Str::random(8), 'fn' => $fn, 'bday' => $bday, 'vcard' => "BEGIN:VCARD\nFN:{$fn}\nEND:VCARD",
        ])->save();

        return $c;
    }

    public function test_internal_share_reaches_recipient_readonly(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'friend@example.test']);
        $book = $this->book($owner->id, 'Family');
        $this->contact($book, 'Alice');

        $this->actingAs($owner)->postJson(route('contacts.shares.store'), ['email' => 'friend@example.test', 'book_id' => $book->id])->assertCreated();
        $this->actingAs($owner)->postJson(route('contacts.shares.store'), ['email' => $owner->email, 'book_id' => $book->id])->assertStatus(422); // self
        $this->actingAs($owner)->postJson(route('contacts.shares.store'), ['email' => 'nobody@example.test', 'book_id' => $book->id])->assertStatus(422); // unknown

        $share = AddressBookShare::query()->where('recipient_id', $recipient->id)->firstOrFail();
        $this->actingAs($recipient)->getJson(route('contacts.shared.index'))->assertOk()->assertJsonPath('shares.0.count', 1)->assertJsonPath('shares.0.name', 'Family');
        $this->actingAs($recipient)->getJson(route('contacts.shared.browse', ['share' => $share->id]))->assertOk()->assertJsonPath('contacts.0.fn', 'Alice');

        // A third user cannot reach the grant.
        $this->actingAs(User::factory()->create())->getJson(route('contacts.shared.browse', ['share' => $share->id]))->assertNotFound();
    }

    public function test_foreign_book_is_rejected(): void
    {
        $owner = User::factory()->create();
        $book = $this->book($owner->id);
        $stranger = User::factory()->create();
        User::factory()->create(['email' => 'x@example.test']);
        $this->actingAs($stranger)->postJson(route('contacts.shares.store'), ['email' => 'x@example.test', 'book_id' => $book->id])->assertNotFound();
    }

    public function test_birthday_feed_enables_and_serves_ics(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user->id);
        $this->contact($book, 'Bob Birthday', '1990-07-06');
        $this->contact($book, 'No Bday', null);

        $res = $this->actingAs($user)->postJson(route('contacts.feed.enable'))->assertOk()->assertJsonPath('enabled', true);
        $url = (string) $res->json('url');
        $this->assertNotSame('', $url);
        $token = (string) $user->fresh()?->birthday_feed_token;

        $ics = $this->get(route('public.contacts.birthdays', ['token' => $token]));
        $ics->assertOk();
        $body = $ics->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $body);
        $this->assertStringContainsString('0706', $body); // Bob's month-day
        $this->assertStringContainsString('Bob Birthday', $body);
        $this->assertSame(1, substr_count($body, 'BEGIN:VEVENT')); // only the contact with a bday

        // Disable → token cleared → feed 404.
        $this->actingAs($user)->deleteJson(route('contacts.feed.disable'))->assertOk();
        $this->get(route('public.contacts.birthdays', ['token' => $token]))->assertNotFound();
    }
}
