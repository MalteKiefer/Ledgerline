<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\AddressBookBackend;
use App\Dav\CalDavBackend;
use App\Dav\DavContext;
use App\Models\AddressBook;
use App\Models\Calendar;
use App\Models\DavCredential;
use App\Models\ResourceShare;
use App\Models\User;
use App\Services\Contacts\DavCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DavSharingTest extends TestCase
{
    use RefreshDatabase;

    private const VEVENT = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\nSUMMARY:S\r\nDTSTART:20260901T100000Z\r\nDTEND:20260901T110000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private function principal(int $userId): string
    {
        return 'principals/'.DavCredential::where('user_id', $userId)->value('username');
    }

    private function share(string $type, string $id, int $owner, int $with, string $perm): void
    {
        ResourceShare::create([
            'shareable_type' => $type, 'shareable_id' => $id,
            'owner_id' => $owner, 'shared_with_user_id' => $with, 'permission' => $perm,
        ]);
    }

    public function test_shared_calendar_appears_for_the_recipient_and_write_share_can_edit(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        app(DavCredentialService::class)->generate($alice->id);
        app(DavCredentialService::class)->generate($bob->id);
        $cal = Calendar::where('user_id', $alice->id)->where('uri', 'default')->firstOrFail();
        $backend = app(CalDavBackend::class);

        $this->share((new Calendar)->getMorphClass(), $cal->id, $alice->id, $bob->id, 'write');

        // Bob's calendar list includes Alice's shared calendar (distinct uri).
        app(DavContext::class)->set($bob->id);
        $uris = array_column($backend->getCalendarsForUser($this->principal($bob->id)), 'uri');
        $this->assertContains('shared-'.$cal->id, $uris);

        // Write share → Bob may create an event in it.
        $this->assertNotNull($backend->createCalendarObject($cal->id, 'e.ics', self::VEVENT));
        $this->assertCount(1, $backend->getCalendarObjects($cal->id));
    }

    public function test_read_only_share_can_view_but_not_write(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        app(DavCredentialService::class)->generate($alice->id);
        app(DavCredentialService::class)->generate($bob->id);
        $cal = Calendar::where('user_id', $alice->id)->where('uri', 'default')->firstOrFail();
        $backend = app(CalDavBackend::class);

        // Alice adds an event, then shares read-only with Bob.
        app(DavContext::class)->set($alice->id);
        $backend->createCalendarObject($cal->id, 'e.ics', self::VEVENT);
        $this->share((new Calendar)->getMorphClass(), $cal->id, $alice->id, $bob->id, 'read');

        app(DavContext::class)->set($bob->id);
        $this->assertCount(1, $backend->getCalendarObjects($cal->id)); // can read
        $this->assertNull($backend->createCalendarObject($cal->id, 'x.ics', self::VEVENT)); // cannot write
    }

    public function test_unshared_user_sees_nothing(): void
    {
        $alice = User::factory()->create();
        $carol = User::factory()->create();
        app(DavCredentialService::class)->generate($alice->id);
        app(DavCredentialService::class)->generate($carol->id);
        $cal = Calendar::where('user_id', $alice->id)->where('uri', 'default')->firstOrFail();
        $backend = app(CalDavBackend::class);

        app(DavContext::class)->set($carol->id);
        $uris = array_column($backend->getCalendarsForUser($this->principal($carol->id)), 'uri');
        $this->assertNotContains('shared-'.$cal->id, $uris);
        $this->assertSame([], $backend->getCalendarObjects($cal->id)); // no access
    }

    public function test_shared_address_book_appears_for_the_recipient(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        app(DavCredentialService::class)->generate($alice->id);
        app(DavCredentialService::class)->generate($bob->id);
        $book = AddressBook::where('user_id', $alice->id)->where('uri', 'default')->firstOrFail();
        $backend = app(AddressBookBackend::class);

        $this->share((new AddressBook)->getMorphClass(), $book->id, $alice->id, $bob->id, 'read');

        app(DavContext::class)->set($bob->id);
        $uris = array_column($backend->getAddressBooksForUser($this->principal($bob->id)), 'uri');
        $this->assertContains('shared-'.$book->id, $uris);
    }
}
