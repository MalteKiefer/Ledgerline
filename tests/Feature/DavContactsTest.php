<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\AddressBookBackend;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Services\Contacts\DavCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_well_known_redirects_to_dav(): void
    {
        $this->get('/.well-known/carddav')->assertRedirect('/dav/');
    }

    public function test_settings_generate_shows_password_once(): void
    {
        $this->signIn();

        $this->post(route('settings.contacts.generate'))
            ->assertRedirect(route('settings.contacts.edit'))
            ->assertSessionHas('dav_password');

        $this->assertDatabaseCount('dav_credentials', 1);
    }
}
