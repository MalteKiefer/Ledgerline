<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\User;
use App\Services\Contacts\ContactDuplicateFinder;
use App\Services\Contacts\ContactWriter;
use App\Services\Contacts\VCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $user): AddressBook
    {
        return AddressBook::create(['user_id' => $user->id, 'uri' => 'default', 'name' => 'Contacts', 'synctoken' => 1]);
    }

    private function make(AddressBook $book, array $data): Contact
    {
        return app(ContactWriter::class)->create($book, $data);
    }

    public function test_finder_groups_contacts_sharing_an_email(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        $this->make($book, ['fn' => 'Alice A', 'emails' => [['value' => 'a@example.com']]]);
        $this->make($book, ['fn' => 'Alicia A', 'emails' => [['value' => 'A@example.com']]]); // same, different case
        $this->make($book, ['fn' => 'Bob B', 'emails' => [['value' => 'bob@example.com']]]);

        $groups = app(ContactDuplicateFinder::class)->forUser($user->id);

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['contacts']);
        $this->assertContains('email', $groups[0]['reasons']);
    }

    public function test_finder_groups_contacts_with_the_same_name(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        $this->make($book, ['first_name' => 'Jon', 'last_name' => 'Doe', 'emails' => [['value' => 'jon1@example.com']]]);
        $this->make($book, ['first_name' => 'Jon', 'last_name' => 'Doe', 'phones' => [['value' => '555']]]);

        $groups = app(ContactDuplicateFinder::class)->forUser($user->id);

        $this->assertCount(1, $groups);
        $this->assertContains('name', $groups[0]['reasons']);
    }

    public function test_transitive_grouping_via_shared_signals(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        // A~B by email, B~C by phone ⇒ {A,B,C}
        $this->make($book, ['fn' => 'A', 'emails' => [['value' => 'shared@example.com']]]);
        $this->make($book, ['fn' => 'B', 'emails' => [['value' => 'shared@example.com']], 'phones' => [['value' => '030 / 12 34 56']]]);
        $this->make($book, ['fn' => 'C', 'phones' => [['value' => '03012 3456']]]); // same digits as B

        $groups = app(ContactDuplicateFinder::class)->forUser($user->id);

        $this->assertCount(1, $groups);
        $this->assertCount(3, $groups[0]['contacts']);
    }

    public function test_merge_unions_fields_into_the_primary_and_deletes_the_rest(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        $a = $this->make($book, ['fn' => 'Kim', 'emails' => [['value' => 'kim@example.com']], 'phones' => [['value' => '111']]]);
        $b = $this->make($book, ['fn' => 'Kim', 'emails' => [['value' => 'kim@example.com'], ['value' => 'kim@work.com']], 'phones' => [['value' => '222']]]);

        $this->actingAs($user)->postJson(route('contacts.duplicates.merge'), [
            'primary_id' => $a->id,
            'ids' => [$a->id, $b->id],
        ])->assertOk();

        $this->assertNull(Contact::find($b->id));
        $survivor = Contact::findOrFail($a->id);
        $parsed = app(VCardService::class)->parse($survivor->vcard);
        $emails = array_map(fn ($e) => strtolower($e['value']), $parsed['emails']);
        $phones = array_map(fn ($p) => $p['value'], $parsed['phones']);
        sort($emails);
        $this->assertSame(['kim@example.com', 'kim@work.com'], $emails);
        $this->assertContains('111', $phones);
        $this->assertContains('222', $phones);
    }

    public function test_merge_rejects_a_contact_of_another_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $a = $this->make($this->book($alice), ['fn' => 'A', 'emails' => [['value' => 'x@example.com']]]);
        $b = $this->make($this->book($bob), ['fn' => 'B', 'emails' => [['value' => 'x@example.com']]]);

        $this->actingAs($alice)->postJson(route('contacts.duplicates.merge'), [
            'primary_id' => $a->id,
            'ids' => [$a->id, $b->id],
        ])->assertForbidden();

        $this->assertNotNull(Contact::find($b->id));
    }

    public function test_dismissed_group_no_longer_appears(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        $a = $this->make($book, ['fn' => 'Dup', 'emails' => [['value' => 'dup@example.com']]]);
        $b = $this->make($book, ['fn' => 'Dup', 'emails' => [['value' => 'dup@example.com']]]);

        $this->actingAs($user)->postJson(route('contacts.duplicates.dismiss'), ['ids' => [$a->id, $b->id]])->assertOk();

        $this->assertCount(0, app(ContactDuplicateFinder::class)->forUser($user->id));
    }
}
