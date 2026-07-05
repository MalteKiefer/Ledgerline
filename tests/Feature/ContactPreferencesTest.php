<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Contacts\ContactWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $user): AddressBook
    {
        return AddressBook::create(['user_id' => $user->id, 'uri' => 'default', 'name' => 'Contacts', 'synctoken' => 1]);
    }

    public function test_settings_persist_sort_and_display_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('contacts.settings'), [
            'sort' => 'last_name', 'display_format' => 'last_first',
        ])->assertOk();

        $s = UserSetting::for($user->id);
        $this->assertSame('last_name', $s->contact_sort);
        $this->assertSame('last_first', $s->contact_display_format);
    }

    public function test_settings_reject_invalid_values(): void
    {
        $user = User::factory()->create();

        // Web validation redirects with session errors (JSON only on api/*).
        $this->actingAs($user)->post(route('contacts.settings'), [
            'sort' => 'middle_name', 'display_format' => 'nope',
        ])->assertSessionHasErrors(['sort', 'display_format']);
    }

    public function test_search_matches_first_and_last_name(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, ['first_name' => 'Daniel', 'last_name' => 'Reim']);
        app(ContactWriter::class)->create($book, ['first_name' => 'Other', 'last_name' => 'Person']);

        $res = $this->actingAs($user)->getJson(route('contacts.data', ['q' => 'daniel']))->assertOk();
        $names = array_column($res->json('contacts'), 'first_name');
        $this->assertSame(['Daniel'], $names);

        // Last name also matches.
        $res2 = $this->actingAs($user)->getJson(route('contacts.data', ['q' => 'reim']))->assertOk();
        $this->assertCount(1, $res2->json('contacts'));
    }

    public function test_search_matches_all_fields_note_and_phone(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, ['fn' => 'Someone', 'note' => 'plays chess on tuesdays', 'phones' => [['value' => '+49 151 22233344']]]);
        app(ContactWriter::class)->create($book, ['fn' => 'Nobody']);

        // Note text lives only in the vCard — search must still find it.
        $this->assertCount(1, $this->actingAs($user)->getJson(route('contacts.data', ['q' => 'chess']))->assertOk()->json('contacts'));
        // Phone number too.
        $this->assertCount(1, $this->actingAs($user)->getJson(route('contacts.data', ['q' => '22233344']))->assertOk()->json('contacts'));
    }

    public function test_suggest_returns_matching_contacts(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, ['first_name' => 'Daniel', 'last_name' => 'Reim']);
        app(ContactWriter::class)->create($book, ['fn' => 'Zzz Nomatch']);

        $res = $this->actingAs($user)->getJson(route('contacts.suggest', ['q' => 'dani']))->assertOk();
        $contacts = $res->json('contacts');
        $this->assertCount(1, $contacts);
        $this->assertSame('Daniel Reim', $contacts[0]['name']);
    }

    public function test_vcard_v3_binary_photo_is_normalised_to_a_data_uri(): void
    {
        $b64 = base64_encode('fake-jpeg-bytes');
        $v3 = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Foo\r\nPHOTO;ENCODING=b;TYPE=JPEG:{$b64}\r\nEND:VCARD\r\n";

        $parsed = app(\App\Services\Contacts\VCardService::class)->parse($v3);

        $this->assertNotNull($parsed['photo']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $parsed['photo']);
    }

    public function test_data_returns_names_settings_and_sorts_by_setting(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        app(ContactWriter::class)->create($book, ['first_name' => 'Zoe', 'last_name' => 'Adams']);
        app(ContactWriter::class)->create($book, ['first_name' => 'Amy', 'last_name' => 'Zeller']);
        UserSetting::for($user->id)->update(['contact_sort' => 'last_name', 'contact_display_format' => 'last_first']);

        $res = $this->actingAs($user)->getJson(route('contacts.data'))->assertOk();

        $res->assertJsonPath('settings.sort', 'last_name');
        $res->assertJsonPath('settings.display_format', 'last_first');
        $contacts = $res->json('contacts');
        // Sorted by last name: Adams before Zeller.
        $this->assertSame('Adams', $contacts[0]['last_name']);
        $this->assertSame('Zeller', $contacts[1]['last_name']);
        $this->assertArrayHasKey('first_name', $contacts[0]);
    }
}
