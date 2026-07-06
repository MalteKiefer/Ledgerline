<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\PersonNamed;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Person;
use App\Services\Contacts\DavCredentialService;
use App\Services\Contacts\VCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function book(int $userId): AddressBook
    {
        return app(DavCredentialService::class)->ensureDefaultBook($userId);
    }

    public function test_store_creates_a_contact_and_bumps_the_sync_token(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);

        $this->postJson(route('contacts.store'), [
            'book_id' => $book->id, 'fn' => 'Jane Doe', 'first_name' => 'Jane', 'last_name' => 'Doe',
            'emails' => [['value' => 'jane@example.com', 'type' => 'work']],
        ])->assertStatus(201);

        $contact = Contact::firstOrFail();
        $this->assertSame('Jane Doe', $contact->fn);
        $this->assertStringContainsString('jane@example.com', $contact->vcard);
        $this->assertSame(2, $book->fresh()->synctoken);
        $this->assertDatabaseHas('dav_changes', ['operation' => 1]);
    }

    public function test_favorite_toggle_persists_and_filters(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);

        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Starred'])->assertStatus(201);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Plain'])->assertStatus(201);
        $starred = Contact::where('fn', 'Starred')->firstOrFail();

        $this->patchJson(route('contacts.favorite', $starred), ['favorite' => true])
            ->assertOk()->assertJsonPath('favorite', true);

        $starred->refresh();
        $this->assertTrue($starred->favorite);
        $this->assertStringContainsString('X-LL-FAVORITE:1', $starred->vcard);

        // The favorites filter returns only the starred card.
        $names = collect($this->getJson(route('contacts.data', ['favorites' => 1]))->json('contacts'))->pluck('fn');
        $this->assertSame(['Starred'], $names->all());

        $this->patchJson(route('contacts.favorite', $starred), ['favorite' => false])
            ->assertOk()->assertJsonPath('favorite', false);
        $this->assertFalse($starred->fresh()->favorite);
    }

    public function test_store_round_trips_addresses_related_and_custom_fields(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);

        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Partner'])->assertStatus(201);
        $partner = Contact::firstOrFail();

        $this->postJson(route('contacts.store'), [
            'book_id' => $book->id, 'fn' => 'Jane',
            'addresses' => [['type' => 'home', 'street' => 'Main St 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'Germany']],
            'related' => [
                ['type' => 'spouse', 'uid' => $partner->uid],
                ['type' => 'friend', 'value' => 'Max'],
            ],
            'custom_fields' => [['label' => 'Insurance', 'value' => 'XY-1']],
            'favorite' => true,
        ])->assertStatus(201);

        $jane = Contact::where('fn', 'Jane')->firstOrFail();
        $show = $this->getJson(route('contacts.show', $jane))->assertOk()->json();

        $this->assertSame('Main St 1', $show['addresses'][0]['street']);
        $this->assertSame('Berlin', $show['addresses'][0]['city']);
        // The linked relation resolves to the partner contact's id + current name.
        $linked = collect($show['related'])->firstWhere('uid', $partner->uid);
        $this->assertSame($partner->id, $linked['contact_id']);
        $this->assertSame('Partner', $linked['name']);
        $free = collect($show['related'])->firstWhere('value', 'Max');
        $this->assertNull($free['contact_id']);
        $this->assertSame('Max', $free['name']);
        $this->assertSame([['label' => 'Insurance', 'value' => 'XY-1']], $show['custom_fields']);
        $this->assertTrue($show['favorite']);
        $this->assertTrue($jane->favorite);
    }

    public function test_editor_pages_render_and_are_owner_scoped(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Jane'])->assertStatus(201);
        $contact = Contact::firstOrFail();

        $this->get(route('contacts.create'))->assertOk()->assertSee('contactEditorPage', false);
        $this->get(route('contacts.edit', $contact))->assertOk()->assertSee('contactEditorPage', false);

        // Another user cannot open the edit page.
        $this->signIn();
        $this->get(route('contacts.edit', $contact))->assertForbidden();
    }

    public function test_geocode_is_owner_only_and_404s_without_an_address(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'NoAddress'])->assertStatus(201);
        $contact = Contact::firstOrFail();

        // No postal address on the card -> 404, no geocoder call.
        $this->getJson(route('contacts.geo', $contact))->assertNotFound();

        // Another user's contact is forbidden.
        $this->signIn();
        $this->getJson(route('contacts.geo', $contact))->assertForbidden();
    }

    public function test_update_keeps_the_uid_and_delete_removes(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'A'])->assertStatus(201);
        $contact = Contact::firstOrFail();
        $uid = app(VCardService::class)->parse($contact->vcard)['uid'];

        $this->putJson(route('contacts.update', $contact), ['book_id' => $book->id, 'fn' => 'A2'])->assertOk();
        $contact->refresh();
        $this->assertSame('A2', $contact->fn);
        $this->assertSame($uid, app(VCardService::class)->parse($contact->vcard)['uid']);

        $this->deleteJson(route('contacts.destroy', $contact))->assertOk();
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_groups_are_mirrored_into_categories(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $group = ContactGroup::create(['user_id' => $user->id, 'name' => 'Friends']);

        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'G', 'group_ids' => [$group->id]])->assertStatus(201);

        $contact = Contact::firstOrFail();
        $this->assertStringContainsString('Friends', $contact->vcard);
        $this->assertTrue($contact->groups()->where('contact_groups.id', $group->id)->exists());
    }

    public function test_group_ids_from_another_user_are_not_attached(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $foreign = ContactGroup::create(['user_id' => 424242, 'name' => 'Victim group']);

        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Mallory', 'group_ids' => [$foreign->id]])
            ->assertStatus(201);

        $contact = Contact::firstOrFail();
        // The forged foreign group id must not create a pivot row.
        $this->assertDatabaseMissing('contact_group', ['contact_id' => $contact->id, 'group_id' => $foreign->id]);
        $this->assertSame(0, $contact->groups()->count());
    }

    public function test_data_is_scoped_to_the_user(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        Contact::create(['address_book_id' => $book->id, 'uri' => 'a.vcf', 'etag' => 'x', 'vcard' => "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Mine\r\nEND:VCARD\r\n", 'fn' => 'Mine']);
        $other = AddressBook::create(['user_id' => 999, 'uri' => 'x', 'name' => 'X', 'synctoken' => 1]);
        Contact::create(['address_book_id' => $other->id, 'uri' => 'b.vcf', 'etag' => 'y', 'vcard' => 'x', 'fn' => 'Theirs']);

        $this->getJson(route('contacts.data'))->assertOk()->assertJsonCount(1, 'contacts')->assertJsonPath('contacts.0.fn', 'Mine');
    }

    public function test_address_book_cannot_delete_the_last_one(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->deleteJson(route('address-books.destroy', $book))->assertStatus(422);
    }

    public function test_address_book_can_be_renamed(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);

        $this->putJson(route('address-books.update', $book), ['name' => 'Work'])->assertOk();

        $this->assertSame('Work', $book->fresh()->name);
    }

    public function test_group_can_be_deleted(): void
    {
        $user = $this->signIn();
        $group = ContactGroup::create(['user_id' => $user->id, 'name' => 'Temp']);

        $this->deleteJson(route('contact-groups.destroy', ['group' => $group]))->assertOk();

        $this->assertDatabaseMissing('contact_groups', ['id' => $group->id]);
    }

    public function test_import_creates_and_dedupes_by_uid(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:u1\r\nFN:One\r\nEND:VCARD\r\n"
            ."BEGIN:VCARD\r\nVERSION:3.0\r\nUID:u2\r\nFN:Two\r\nEND:VCARD\r\n";

        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
            ->assertOk()->assertJson(['created' => 2]);
        $this->assertDatabaseCount('contacts', 2);

        // Re-import → dedupe (update, not create).
        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
            ->assertOk()->assertJson(['created' => 0, 'updated' => 2]);
        $this->assertDatabaseCount('contacts', 2);
    }

    public function test_export_streams_vcards(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        Contact::create(['address_book_id' => $book->id, 'uri' => 'a.vcf', 'etag' => 'x', 'vcard' => "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Exp\r\nEND:VCARD\r\n", 'fn' => 'Exp']);

        $res = $this->get(route('contacts.export'));
        $res->assertOk();
        $this->assertStringContainsString('FN:Exp', $res->streamedContent());
    }

    public function test_avatar_upload_embeds_a_photo(): void
    {
        Storage::fake('files');
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Pic'])->assertStatus(201);
        $contact = Contact::firstOrFail();

        $this->post(route('contacts.avatar.upload', $contact), ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)])->assertOk();

        $this->assertTrue($contact->fresh()->has_photo);
        $this->assertStringContainsString('PHOTO', $contact->fresh()->vcard);
        $this->get(route('contacts.avatar', $contact))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_naming_a_person_links_or_creates_a_contact(): void
    {
        $user = $this->signIn();
        $this->book($user->id);
        $person = Person::create(['name' => null]);

        PersonNamed::dispatch($person->id, 'Alice Example', $user->id);

        $person->refresh();
        $this->assertNotNull($person->contact_id);
        $this->assertDatabaseHas('contacts', ['fn' => 'Alice Example']);

        // Naming another person the same name reuses the existing contact.
        $p2 = Person::create(['name' => null]);
        PersonNamed::dispatch($p2->id, 'Alice Example', $user->id);
        $this->assertSame($person->contact_id, $p2->fresh()->contact_id);
        $this->assertDatabaseCount('contacts', 1);
    }
}
