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
        // Post-pivot the modules are plaintext-relational: a note is one owned row
        // (user_id FK, cascade on delete). Used here to prove per-user erase +
        // isolation through PurgeUserAccount.
        return (new Note)->forceFill([
            'user_id' => $user->id,
            'title' => $title,
            'body' => 'body',
        ]);
    }

    public function test_export_streams_a_zip_of_all_modules(): void
    {
        $user = User::factory()->create();
        $this->ownedNote($user, 'mine')->save();

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
        $this->ownedNote($user, 'secret')->save();
        $otherUser = User::factory()->create();
        $this->ownedNote($otherUser, 'keep')->save();

        app(PurgeUserAccount::class)->handle($user);

        $this->assertNull(User::find($user->id));
        $this->assertNull(Note::withTrashed()->where('user_id', $user->id)->first());
        $this->assertNotNull(Note::query()->where('user_id', $otherUser->id)->first());
        $this->assertNotNull(User::find($otherUser->id));
    }
}
