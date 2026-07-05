<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\AddressBookBackend;
use App\Dav\AuthBackend;
use App\Dav\DavContext;
use App\Dav\PrincipalBackend;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\DavCredential;
use App\Services\Contacts\DavCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin;
use Sabre\DAV\Server;
use Sabre\DAVACL\PrincipalCollection;
use Tests\TestCase;

class DavContactsTest extends TestCase
{
    use RefreshDatabase;

    private const VCARD = "BEGIN:VCARD\r\nVERSION:4.0\r\nUID:abc-1\r\nFN:Jane Doe\r\nN:Doe;Jane;;;\r\nEMAIL:jane@example.com\r\nTEL:+49 111\r\nORG:Acme\r\nEND:VCARD\r\n";

    public function test_credentials_generate_and_verify(): void
    {
        $svc = app(DavCredentialService::class);
        $result = $svc->generate(1);

        $this->assertNotEmpty($result['password']);
        $this->assertNotNull($svc->verify($result['credential']->username, $result['password']));
        $this->assertNull($svc->verify($result['credential']->username, 'wrong'));
        // A default address book was created.
        $this->assertDatabaseHas('address_books', ['user_id' => 1, 'uri' => 'default']);
    }

    public function test_carddav_backend_stores_reads_updates_and_deletes_cards(): void
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1); // authenticated as user 1
        $book = AddressBook::where('user_id', 1)->firstOrFail();
        $backend = app(AddressBookBackend::class);

        // Create.
        $etag = $backend->createCard($book->id, 'jane.vcf', self::VCARD);
        $this->assertNotNull($etag);
        $contact = Contact::where('address_book_id', $book->id)->where('uri', 'jane.vcf')->firstOrFail();
        $this->assertSame('Jane Doe', $contact->fn);
        $this->assertSame('Doe', $contact->last_name);
        $this->assertContains('jane@example.com', $contact->emails);
        $this->assertSame(2, $book->fresh()->synctoken);
        $this->assertDatabaseHas('dav_changes', ['uri' => 'jane.vcf', 'operation' => 1]);

        // Read.
        $card = $backend->getCard($book->id, 'jane.vcf');
        $this->assertSame(self::VCARD, $card['carddata']);

        // Sync since token 1 → the added card.
        $changes = $backend->getChangesForAddressBook($book->id, '1', 1);
        $this->assertContains('jane.vcf', $changes['added']);

        // Update.
        $backend->updateCard($book->id, 'jane.vcf', str_replace('Acme', 'Globex', self::VCARD));
        $this->assertSame('Globex', $contact->fresh()->org);
        $this->assertSame(3, $book->fresh()->synctoken);

        // Delete.
        $this->assertTrue($backend->deleteCard($book->id, 'jane.vcf'));
        $this->assertFalse($backend->getCard($book->id, 'jane.vcf'));
    }

    public function test_backend_denies_access_to_another_users_book(): void
    {
        app(DavCredentialService::class)->generate(1);
        $mine = AddressBook::where('user_id', 1)->firstOrFail();
        $theirs = AddressBook::create(['user_id' => 2, 'uri' => 'default', 'name' => 'Theirs', 'synctoken' => 1]);

        // Authenticated as user 1.
        app(DavContext::class)->set(1);
        $backend = app(AddressBookBackend::class);

        // Own book: writable.
        $this->assertNotNull($backend->createCard($mine->id, 'a.vcf', self::VCARD));

        // Someone else's book: every operation is denied.
        $this->assertNull($backend->createCard($theirs->id, 'x.vcf', self::VCARD));
        $this->assertFalse($backend->getCard($theirs->id, 'x.vcf'));
        $this->assertSame([], $backend->getCards($theirs->id));
        $this->assertFalse($backend->deleteCard($theirs->id, 'x.vcf'));
        $this->assertNull($backend->getChangesForAddressBook($theirs->id, null, 1));
        $this->assertDatabaseCount('contacts', 1); // only the one in the own book
    }

    public function test_well_known_redirects_to_dav(): void
    {
        $this->get('/.well-known/carddav')->assertRedirect('/dav/');
        // RFC 6764: clients often discover via PROPFIND, not GET.
        $this->call('PROPFIND', '/.well-known/carddav')->assertRedirect('/dav/');
    }

    public function test_sabre_server_tree_builds(): void
    {
        // Guards against wrong sabre class names in DavController (the tree is
        // built the same way; DavController itself exits, so exercise the nodes).
        $principals = app(PrincipalBackend::class);
        $cards = app(AddressBookBackend::class);

        $server = new Server([
            new PrincipalCollection($principals),
            new AddressBookRoot($principals, $cards),
        ]);
        $server->addPlugin(new \Sabre\DAV\Auth\Plugin(app(AuthBackend::class)));
        $server->addPlugin(new Plugin);
        $server->addPlugin(new \Sabre\DAV\Sync\Plugin);

        $this->assertInstanceOf(Server::class, $server);
    }

    public function test_settings_generate_shows_password_once(): void
    {
        $this->signIn();

        // generate() redirects back to the referring page (profile or settings).
        $this->from(route('settings.contacts.edit'))
            ->post(route('settings.contacts.generate'))
            ->assertRedirect(route('settings.contacts.edit'))
            ->assertSessionHas('dav_password');

        $this->assertDatabaseCount('dav_credentials', 1);
    }

    public function test_profile_page_shows_dav_sync_details(): void
    {
        $this->signIn();
        $this->post(route('settings.contacts.generate'));

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee(url('/dav/'))
            ->assertSee(route('settings.contacts.profile'));
    }

    public function test_apple_profile_downloads_a_carddav_mobileconfig(): void
    {
        $this->signIn();
        $this->post(route('settings.contacts.generate'));
        $username = DavCredential::firstOrFail()->username;

        $res = $this->get(route('settings.contacts.profile'));
        $res->assertOk()->assertHeader('Content-Type', 'application/x-apple-aspen-config; charset=utf-8');
        $body = $res->getContent();
        $this->assertStringContainsString('com.apple.carddav.account', $body);
        $this->assertStringContainsString('CardDAVHostName', $body);
        $this->assertStringContainsString($username, $body);
        // The profile also bundles a CalDAV account (one profile for both).
        $this->assertStringContainsString('com.apple.caldav.account', $body);
        $this->assertStringContainsString('CalDAVHostName', $body);
    }

    public function test_apple_profile_requires_credentials(): void
    {
        $this->signIn();
        $this->get(route('settings.contacts.profile'))->assertNotFound();
    }

    public function test_settings_page_shows_a_qr_when_enabled(): void
    {
        $this->signIn();
        $this->post(route('settings.contacts.generate'));

        $this->get(route('settings.contacts.edit'))->assertOk()->assertSee('<svg', false);
    }
}
