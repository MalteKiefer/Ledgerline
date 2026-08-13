<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Vue SPA consumes contacts strictly via /api/v1 (module:contacts + device
 * token). These mirror the web ContactController JSON methods — verify the
 * device-auth gate, the data snapshot, create/show/delete, and owner-scope.
 */
class ApiContactsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('iphone', ['device'])->plainTextToken, 'Accept' => 'application/json'];
    }

    private function book(User $user): AddressBook
    {
        return AddressBook::create(['user_id' => $user->id, 'name' => 'Default', 'uri' => 'default-'.$user->id]);
    }

    public function test_data_requires_device_token(): void
    {
        $this->getJson('/api/v1/contacts/data')->assertUnauthorized();
    }

    public function test_data_returns_snapshot(): void
    {
        $user = User::factory()->create();
        $this->book($user);
        $this->getJson('/api/v1/contacts/data', $this->auth($user))
            ->assertOk()
            ->assertJsonStructure(['contacts', 'books', 'groups']);
    }

    public function test_create_show_delete_roundtrip(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        $created = $this->postJson('/api/v1/contacts', [
            'book_id' => (string) $book->id,
            'fn' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'emails' => [['value' => 'ada@example.com', 'type' => 'home']],
        ], $this->auth($user))->assertCreated()->json('id');
        $this->assertIsString($created);
        $id = $created;

        $this->getJson('/api/v1/contacts/'.$id, $this->auth($user))
            ->assertOk()->assertJsonFragment(['fn' => 'Ada Lovelace']);

        $this->deleteJson('/api/v1/contacts/'.$id, [], $this->auth($user))->assertOk();
    }

    public function test_owner_cannot_read_foreign_contact(): void
    {
        // Seed the owner's contact directly via Eloquent so the ONLY HTTP request
        // in this test is the intruder's — otherwise the stateful test client
        // reuses the first request's session (a harness artifact, not prod: real
        // SPA clients each have their own session, mobile Bearer carries none).
        $owner = User::factory()->create();
        $book = $this->book($owner);
        $contact = Contact::create([
            'address_book_id' => $book->id,
            'uri' => 'secret.vcf',
            'etag' => 'e',
            'uid' => 'secret-uid',
            'vcard' => "BEGIN:VCARD\nVERSION:4.0\nFN:Secret\nEND:VCARD\n",
        ]);

        $intruder = User::factory()->create();
        $this->getJson("/api/v1/contacts/{$contact->id}", $this->auth($intruder))
            ->assertStatus(403);
    }
}
