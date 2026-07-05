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
