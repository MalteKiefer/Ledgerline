<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\ContactSyncSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSyncSourceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken, 'Accept' => 'application/json'];
    }

    public function test_carddav_replica_encrypts_credentials_and_never_returns_them(): void
    {
        $user = User::factory()->create();
        $book = AddressBook::create(['user_id' => $user->id, 'name' => 'Contacts', 'uri' => 'contacts']);
        $response = $this->postJson('/api/v1/contacts/sources', [
            'name' => 'iCloud', 'address_book_id' => $book->id, 'provider' => 'icloud',
            'endpoint' => 'https://contacts.example.test/addressbooks/me/default/',
            'username' => 'owner@example.test', 'password' => 'app-specific-password',
        ], $this->auth($user))->assertCreated();

        $source = ContactSyncSource::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('app-specific-password', $source->password);
        $this->assertStringNotContainsString('app-specific-password', (string) $source->getRawOriginal('password'));
        $response->assertJsonMissing(['password' => 'app-specific-password']);
        $this->getJson('/api/v1/contacts/sources', $this->auth($user))
            ->assertOk()->assertJsonMissing(['password' => 'app-specific-password']);
    }

    public function test_replica_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $book = AddressBook::create(['user_id' => $owner->id, 'name' => 'Contacts', 'uri' => 'contacts']);
        $source = ContactSyncSource::create([
            'user_id' => $owner->id, 'address_book_id' => $book->id, 'name' => 'Private',
            'provider' => 'carddav', 'endpoint' => 'https://contacts.example.test/book/', 'auth_type' => 'basic',
            'username' => 'owner', 'password' => 'secret',
        ]);
        $intruder = User::factory()->create();

        $this->postJson('/api/v1/contacts/sources/'.$source->id.'/sync', [], $this->auth($intruder))->assertNotFound();
        $this->getJson('/api/v1/contacts/sources', $this->auth($intruder))->assertOk()->assertJsonPath('sources', []);
    }
}
