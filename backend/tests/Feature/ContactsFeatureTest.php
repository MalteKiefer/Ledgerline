<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use App\Services\Contacts\ContactWriter;
use App\Services\Contacts\VCardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function book(int $userId): AddressBook
    {
        return AddressBook::create([
            'user_id' => $userId,
            'name' => 'Contacts',
            'uri' => 'contacts-'.$userId.'-'.Str::lower(Str::random(4)),
            'synctoken' => 1,
        ]);
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

    public function test_anniversaries_round_trip_and_address_book_rename_delete(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);

        // Anniversaries round-trip (the SPA editor's new repeater).
        $this->postJson(route('contacts.store'), [
            'book_id' => $book->id, 'fn' => 'Ann',
            'anniversaries' => [['date' => '2015-06-01', 'label' => 'Wedding']],
        ])->assertStatus(201);
        $show = $this->getJson(route('contacts.show', Contact::firstOrFail()))->assertOk()->json();
        $this->assertSame('Wedding', $show['anniversaries'][0]['label']);

        // Address book rename + delete via the API endpoints the SPA now calls.
        $this->putJson(route('api.address-books.update', $book->id), ['name' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $book->fresh()->name);

        $second = $this->book($user->id);
        $this->deleteJson(route('api.address-books.destroy', $second->id))->assertOk();
        $this->assertDatabaseMissing('address_books', ['id' => $second->id]);
    }

    public function test_full_update_sets_all_editor_fields_and_groups(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $group = ContactGroup::create(['user_id' => $user->id, 'name' => 'Friends']);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Bare'])->assertStatus(201);
        $jane = Contact::firstOrFail();

        // The exact payload shape the SPA editor now submits.
        $this->putJson(route('contacts.update', $jane), [
            'first_name' => 'Jane', 'last_name' => 'Doe', 'nickname' => 'Jay', 'bday' => '1990-05-01',
            'urls' => [['value' => 'https://jane.example', 'type' => 'home']],
            'addresses' => [['type' => 'home', 'street' => 'Main St 1', 'zip' => '10115', 'city' => 'Berlin', 'region' => '', 'country' => 'Germany']],
            'custom_fields' => [['label' => 'Insurance', 'value' => 'XY-1']],
            'group_ids' => [(string) $group->id],
        ])->assertOk();

        $show = $this->getJson(route('contacts.show', $jane))->assertOk()->json();
        $this->assertSame('Jay', $show['nickname']);
        $this->assertSame('1990-05-01', $show['bday']);
        $this->assertSame('https://jane.example', $show['urls'][0]['value']);
        $this->assertSame('Main St 1', $show['addresses'][0]['street']);
        $this->assertSame([['label' => 'Insurance', 'value' => 'XY-1']], $show['custom_fields']);
        $this->assertEqualsCanonicalizing([$group->id], $show['group_ids']);
    }

    public function test_partial_update_preserves_omitted_sections_and_groups(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $group = ContactGroup::create(['user_id' => $user->id, 'name' => 'Work']);

        $this->postJson(route('contacts.store'), [
            'book_id' => $book->id, 'fn' => 'Jane', 'bday' => '1990-05-01',
            'addresses' => [['type' => 'home', 'street' => 'Main St 1', 'city' => 'Berlin']],
            'urls' => [['value' => 'https://jane.example']],
            'custom_fields' => [['label' => 'Insurance', 'value' => 'XY-1']],
            'group_ids' => [(string) $group->id],
        ])->assertStatus(201);
        $jane = Contact::where('fn', 'Jane')->firstOrFail();

        // A partial edit — exactly what the current SPA editor sends (name only,
        // no addresses/urls/bday/custom/group_ids) — must not wipe the rest.
        $this->putJson(route('contacts.update', $jane), ['fn' => 'Jane Renamed'])->assertOk();

        $show = $this->getJson(route('contacts.show', $jane))->assertOk()->json();
        $this->assertSame('Jane Renamed', $show['fn']);
        $this->assertSame('Main St 1', $show['addresses'][0]['street']);
        $this->assertSame('1990-05-01', $show['bday']);
        $this->assertSame([['label' => 'Insurance', 'value' => 'XY-1']], $show['custom_fields']);
        $this->assertStringContainsString('https://jane.example', $jane->fresh()->vcard);
        $this->assertEqualsCanonicalizing([$group->id], $show['group_ids']);
    }

    public function test_editor_paths_serve_the_shell_and_contact_data_is_owner_scoped(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Jane'])->assertStatus(201);
        $contact = Contact::firstOrFail();

        // SPA-only: the editor UI lives in the SPA, so the create/edit paths
        // serve the shell (200) and vue-router renders the form client-side.
        $this->get('/contacts/create')->assertOk()->assertSee('id="app"', false);
        $this->get('/contacts/'.$contact->id.'/edit')->assertOk()->assertSee('id="app"', false);

        // Owner-scoping now lives on the data endpoint: another user cannot read
        // the contact JSON.
        $this->signIn();
        $this->getJson(route('contacts.show', $contact))->assertForbidden();
    }

    public function test_updating_a_contact_keeps_its_photo(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);

        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Jane'])->assertStatus(201);
        $contact = Contact::firstOrFail();

        // Attach a photo the way the avatar endpoint does (vCard PHOTO data URI).
        $vcards = app(VCardService::class);
        $data = $vcards->parse($contact->vcard);
        $data['photo'] = 'data:image/jpeg;base64,'.base64_encode('img-bytes');
        app(ContactWriter::class)->update($contact, $data, []);
        $this->assertTrue($contact->fresh()->has_photo);

        // A regular editor save (no photo in the payload) must not drop it.
        $this->putJson(route('contacts.update', $contact), ['fn' => 'Jane Renamed'])->assertOk();

        $contact->refresh();
        $this->assertSame('Jane Renamed', $contact->fn);
        $this->assertTrue($contact->has_photo);
        $this->assertStringContainsString('PHOTO', $contact->vcard);
    }

    public function test_view_path_serves_the_shell_and_contact_data_is_owner_scoped(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'Jane'])->assertStatus(201);
        $contact = Contact::firstOrFail();

        // SPA-only: the contact detail UI lives in the SPA; the view path serves
        // the shell (200).
        $this->get('/contacts/'.$contact->id.'/view')->assertOk()->assertSee('id="app"', false);

        // A non-owner cannot read the contact JSON (owner-scoped data endpoint).
        $this->signIn();
        $this->getJson(route('contacts.show', $contact))->assertForbidden();
    }

    public function test_bulk_destroy_deletes_own_contacts_and_ignores_foreign_ids(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'A'])->assertStatus(201);
        $this->postJson(route('contacts.store'), ['book_id' => $book->id, 'fn' => 'B'])->assertStatus(201);
        $mine = Contact::pluck('id')->all();

        $other = User::factory()->create();
        $otherBook = $this->book($other->id);
        $foreign = Contact::withoutGlobalScopes()->create([
            'address_book_id' => $otherBook->id, 'uri' => 'x.vcf', 'etag' => 'e',
            'vcard' => "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Foreign\r\nEND:VCARD\r\n", 'fn' => 'Foreign',
        ]);

        $this->deleteJson(route('contacts.bulk-destroy'), ['ids' => array_merge($mine, [$foreign->id])])
            ->assertOk()->assertJsonPath('deleted', 2);

        $this->assertSame(0, Contact::whereIn('id', $mine)->count());
        $this->assertNotNull(Contact::withoutGlobalScopes()->find($foreign->id));
        // Deletions are logged for DAV sync.
        $this->assertSame(2, DB::table('dav_changes')->where('operation', 3)->count());
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
        $foreign = ContactGroup::create(['user_id' => User::factory()->create()->id, 'name' => 'Victim group']);

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
        $other = AddressBook::create(['user_id' => User::factory()->create()->id, 'uri' => 'x', 'name' => 'X', 'synctoken' => 1]);
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

    public function test_reimport_without_uid_dedupes_by_name_and_contacts(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        // No UID: a fresh random one would be minted each run — must dedupe by
        // the natural key (name + email/phone) instead of creating a duplicate.
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Max Muster\r\nN:Muster;Max;;;\r\n"
            ."EMAIL:max@example.com\r\nTEL:+49 175 4182881\r\nBDAY:19870630\r\nEND:VCARD\r\n";

        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
            ->assertOk()->assertJson(['created' => 1]);
        $this->assertDatabaseCount('contacts', 1);
        // Birthday is denormalised on import (year-agnostic MM-DD column).
        $this->assertSame('06-30', Contact::where('address_book_id', $book->id)->first()->bday);

        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
            ->assertOk()->assertJson(['created' => 0, 'updated' => 1]);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_reimport_updates_under_prevented_lazy_loading(): void
    {
        // The bulk-update path must not lazy-load the address book relation
        // (disabled app-wide) or every re-imported card is silently skipped.
        Model::preventLazyLoading(true);
        try {
            $user = $this->signIn();
            $book = $this->book($user->id);
            $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:u1\r\nFN:One\r\nEMAIL:one@example.com\r\nEND:VCARD\r\n";
            $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
                ->assertOk()->assertJson(['created' => 1]);
            $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
                ->assertOk()->assertJson(['created' => 0, 'updated' => 1, 'skipped' => 0]);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_reimport_contactless_card_dedupes_by_name_org_bday(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        // No email/phone: matched on name + org + birthday, not duplicated.
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Tahnee Wilms\r\nN:Wilms;Tahnee;;;\r\n"
            ."ORG:Finanz Informatik;\r\nBDAY:19890209\r\nEND:VCARD\r\n";
        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)]);
        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('c.vcf', $vcf)])
            ->assertOk()->assertJson(['created' => 0, 'updated' => 1]);
        $this->assertDatabaseCount('contacts', 1);
        // ORG's trailing structured-empty part is stripped in the column.
        $this->assertSame('Finanz Informatik', Contact::where('address_book_id', $book->id)->first()->org);
    }

    public function test_parser_fixes_apple_export_quirks(): void
    {
        $svc = app(VCardService::class);
        // Address packed into the extended component, duplicate phone, ORG with a
        // trailing empty unit, and an Apple year-less (1604) birthday.
        $vcf = "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Q\r\nN:Q;;;;\r\n"
            ."ORG:ACME GmbH;\r\n"
            ."ADR;TYPE=WORK:;Musterstr 1\\nBerlin\\n10115\\nDE;;;;;\r\n"
            ."TEL;TYPE=CELL:+49 30 12345\r\nTEL;TYPE=VOICE:+49 30 12345\r\n"
            ."item1.TEL:+49 30 6789\r\nitem1.X-ABLabel:Zentrale\r\n"
            ."BDAY;X-APPLE-OMIT-YEAR=1604:1604-02-21\r\nEND:VCARD\r\n";
        $p = $svc->parse($vcf);

        $this->assertSame('ACME GmbH', $p['org']);
        // Packed ext split into structured street/city/zip/country.
        $this->assertSame('Musterstr 1', $p['addresses'][0]['street']);
        $this->assertSame('Berlin', $p['addresses'][0]['city']);
        $this->assertSame('10115', $p['addresses'][0]['zip']);
        // Duplicate phone collapsed to one; item-grouped label becomes the type.
        $phones = $p['phones'];
        $this->assertCount(2, $phones);
        $this->assertSame('Zentrale', $phones[1]['type']);
        // Denormalised birthday keeps only MM-DD (year-agnostic).
        $this->assertSame('02-21', $svc->denormalize($vcf)['bday']);
    }

    public function test_import_keeps_inline_photo_but_drops_remote_url_photo(): void
    {
        $user = $this->signIn();
        $book = $this->book($user->id);
        // A 1x1 JPEG (FF D8 ... FF D9) inline, base64 — the Apple "ENCODING=b" form.
        $jpeg = base64_encode("\xFF\xD8\xFF\xE0".str_repeat("\x00", 200)."\xFF\xD9");
        $apple = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Apple Photo\r\nN:Photo;Apple;;;\r\n"
            ."PHOTO;ENCODING=b;TYPE=JPEG:$jpeg\r\nEND:VCARD\r\n";
        // Google exports an auth-gated remote URL that can't be served.
        $google = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Google Photo\r\nN:Photo;Google;;;\r\n"
            ."PHOTO:https://lh3.googleusercontent.com/contacts/AG6tpzExample\r\nEND:VCARD\r\n";

        $this->post(route('contacts.import'), ['book_id' => $book->id, 'file' => UploadedFile::fake()->createWithContent('a.vcf', $apple.$google)])
            ->assertOk()->assertJson(['created' => 2]);

        $vcards = app(VCardService::class);
        $appleC = Contact::where('fn', 'Apple Photo')->firstOrFail();
        $googleC = Contact::where('fn', 'Google Photo')->firstOrFail();
        // Inline photo survives as a servable data: URI; remote URL is dropped.
        $this->assertTrue($appleC->has_photo);
        $this->assertStringStartsWith('data:image/jpeg;base64,', (string) $vcards->parse($appleC->vcard)['photo']);
        $this->assertFalse($googleC->has_photo);
        $this->assertNull($vcards->parse($googleC->vcard)['photo']);
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

        $this->post(route('contacts.avatar', $contact), ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)])->assertOk();

        $this->assertTrue($contact->fresh()->has_photo);
        $this->assertStringContainsString('PHOTO', $contact->fresh()->vcard);
        $this->get(route('contacts.avatar', $contact))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }
}
