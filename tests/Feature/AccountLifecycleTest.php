<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\PurgeUserAccount;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ownedNote(User $user, string $title): Note
    {
        // Ownership is assigned from the authenticated user (AssignsOwner), not
        // mass-assigned. saveQuietly() skips that hook so the explicit user_id
        // sticks without needing an authenticated request.
        $note = new Note(['title' => $title, 'content' => 'x']);
        $note->user_id = $user->id;
        $note->saveQuietly();

        return $note;
    }

    public function test_export_streams_a_zip_of_all_modules(): void
    {
        $user = User::factory()->create();
        $this->ownedNote($user, 'Mine');

        $res = $this->actingAs($user)->get(route('account.export'));
        $res->assertOk();
        $this->assertSame('application/zip', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.zip', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_wrong_confirmation_does_not_delete(): void
    {
        $user = User::factory()->create(['email' => 'gdpr@example.com']);

        $this->actingAs($user)->delete(route('account.destroy'), ['confirmation' => 'nope'])
            ->assertSessionHasErrors('confirmation');
        $this->assertNotNull(User::find($user->id));
    }

    public function test_purge_action_erases_the_user_and_their_data(): void
    {
        $user = User::factory()->create(['email' => 'gdpr@example.com']);
        $note = $this->ownedNote($user, 'Secret');
        $otherUser = User::factory()->create();
        $otherNote = $this->ownedNote($otherUser, 'Keep');

        app(PurgeUserAccount::class)->handle($user);

        $this->assertNull(User::find($user->id));
        $this->assertNull(Note::withoutGlobalScopes()->find($note->id));
        $this->assertNotNull(Note::withoutGlobalScopes()->find($otherNote->id));
        $this->assertNotNull(User::find($otherUser->id));
    }
}
