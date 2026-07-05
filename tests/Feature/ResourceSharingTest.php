<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Calendar;
use App\Models\Note;
use App\Models\ResourceShare;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResourceSharingTest extends TestCase
{
    use RefreshDatabase;

    private function noteOf(User $u, string $title): Note
    {
        $this->actingAs($u);
        $note = Note::create(['title' => $title, 'content' => 'c']);
        $this->app['auth']->forgetGuards();

        return $note;
    }

    public function test_owner_can_share_a_note_read_only_and_sharee_sees_but_cannot_edit(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Shared plan');

        // Alice shares read-only with Bob.
        $this->actingAs($alice)
            ->postJson(route('shares.store'), ['type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read'])
            ->assertCreated();

        // Bob now sees the note...
        $this->actingAs($bob);
        $this->assertSame(1, Note::count());
        $this->getJson(route('notes.data'))->assertOk()->assertJsonFragment(['title' => 'Shared plan']);

        // ...but a read-only sharee cannot edit it (central write guard → 403).
        $this->putJson(route('notes.update', $note->id), ['title' => 'Hacked', 'content' => 'x'])->assertForbidden();
        $this->assertSame('Shared plan', Note::withoutGlobalScopes()->find($note->id)->title);
    }

    public function test_write_share_allows_the_sharee_to_edit(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Doc');

        $this->actingAs($alice)
            ->postJson(route('shares.store'), ['type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'write'])
            ->assertCreated();

        $this->actingAs($bob)
            ->putJson(route('notes.update', $note->id), ['title' => 'Edited by Bob', 'content' => 'x'])
            ->assertOk();
        $this->assertSame('Edited by Bob', Note::withoutGlobalScopes()->find($note->id)->title);
    }

    public function test_a_third_user_still_cannot_see_the_note(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $note = $this->noteOf($alice, 'Private');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();

        $this->actingAs($carol);
        $this->assertSame(0, Note::count());
    }

    public function test_only_the_owner_can_share_a_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Mine');

        // Bob (not the owner) cannot share Alice's note.
        $this->actingAs($bob)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $alice->email, 'permission' => 'read',
        ])->assertForbidden();
    }

    public function test_a_sharee_sync_cannot_destroy_the_owners_files(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $file = StoredFile::create([
            'id' => (string) Str::uuid(), 'name' => 'a.txt',
            'mime' => 'text/plain', 'size' => 1, 'blob' => (string) Str::uuid(), 'tags' => [],
        ]);
        // Even a WRITE share must not let a full-replace sync delete the owner's file.
        $this->postJson(route('shares.store'), ['type' => 'files', 'id' => $file->id, 'email' => $bob->email, 'permission' => 'write'])->assertCreated();

        $this->actingAs($bob);
        // Bob's own tree is empty; the shared file is NOT in his manifest…
        $this->getJson(route('files.data'))->assertOk()->assertJsonMissing(['name' => 'a.txt']);
        // …and posting an empty manifest must not touch Alice's file.
        $this->putJson(route('files.sync'), ['folders' => [], 'files' => []])->assertOk();
        $this->assertDatabaseHas('files', ['id' => $file->id, 'deleted_at' => null]);
    }

    public function test_owner_can_share_a_calendar_and_address_book(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $calendar = Calendar::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Team', 'components' => ['VEVENT'], 'synctoken' => 1]);
        $book = AddressBook::create(['user_id' => $alice->id, 'uri' => 'default', 'name' => 'Shared', 'synctoken' => 1]);

        $this->actingAs($alice)->postJson(route('shares.store'), ['type' => 'calendars', 'id' => $calendar->id, 'email' => $bob->email, 'permission' => 'write'])->assertCreated();
        $this->actingAs($alice)->postJson(route('shares.store'), ['type' => 'address-books', 'id' => $book->id, 'email' => $bob->email, 'permission' => 'read'])->assertCreated();

        // Bob now sees both via the owned-or-shared scope.
        $this->actingAs($bob);
        $this->assertNotNull(Calendar::find($calendar->id));
        $this->assertNotNull(AddressBook::find($book->id));
    }

    public function test_a_read_only_calendar_cannot_be_shared(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $holidays = Calendar::create(['user_id' => $alice->id, 'uri' => 'holidays', 'name' => 'Holidays', 'components' => ['VEVENT'], 'synctoken' => 1, 'read_only' => true]);

        $this->actingAs($alice)->postJson(route('shares.store'), ['type' => 'calendars', 'id' => $holidays->id, 'email' => $bob->email, 'permission' => 'read'])
            ->assertStatus(422);
    }

    public function test_internal_share_notifies_the_recipient_in_app(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Plan');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated()->assertJsonStructure(['ok', 'id', 'link']);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $bob->id, 'category' => 'share']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $alice->id, 'category' => 'share']);
    }

    public function test_share_email_is_rejected_without_a_mail_server(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Plan');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read',
        ]);
        $share = ResourceShare::firstOrFail();

        // No SMTP configured → the mail-share option is refused (offer copy link instead).
        $this->actingAs($alice)->postJson(route('shares.email', $share->id))->assertStatus(422);

        // A non-owner cannot trigger it either.
        $this->actingAs($bob)->postJson(route('shares.email', $share->id))->assertForbidden();
    }

    public function test_owner_can_revoke_a_share(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteOf($alice, 'Temp');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'notes', 'id' => $note->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();
        $share = ResourceShare::firstOrFail();

        $this->actingAs($alice)->deleteJson(route('shares.destroy', $share->id))->assertOk();
        $this->actingAs($bob);
        $this->assertSame(0, Note::count());
    }
}
