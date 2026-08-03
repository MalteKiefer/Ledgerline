<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Archived mail is immutable: it is never hard-deleted, and deleting the IMAP
 * ACCOUNT config must not delete the mail. "Delete" is only a soft hide.
 */
class MailImmutableTest extends TestCase
{
    use RefreshDatabase;

    private function message(User $user, ?MailAccount $account, bool $seen = false): MailMessage
    {
        $m = new MailMessage([
            'id' => (string) Str::uuid(),
            'account_id' => $account?->id,
            'folder' => 'INBOX',
            'seen' => $seen,
            'content_hash' => hash('sha256', Str::uuid()->toString()),
            'size' => 3,
            'sealed_key' => '{}',
        ]);
        $m->user_id = $user->id;
        $m->save();

        return $m;
    }

    public function test_deleting_the_mail_account_keeps_the_archived_messages(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->message($user, $account);

        $account->delete();

        // The message survives (detached: account_id becomes null).
        $this->assertDatabaseHas('mail_messages', ['id' => $message->id]);
        $this->assertNull($message->fresh()->account_id);
    }

    public function test_trash_hides_and_restore_unhides_owner_scoped(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->message($user, $account);

        // Default list excludes nothing yet.
        $this->actingAs($user)->getJson('/api/v1/mail/messages')->assertJsonPath('meta.total', 1);

        $this->actingAs($user)->postJson('/api/v1/mail/messages/trash', ['ids' => [$message->id]])
            ->assertOk()->assertJson(['updated' => 1]);
        $this->assertNotNull($message->fresh()->trashed_at);

        // Hidden from the default list; visible in the trash view.
        $this->actingAs($user)->getJson('/api/v1/mail/messages')->assertJsonPath('meta.total', 0);
        $this->actingAs($user)->getJson('/api/v1/mail/messages?trashed=1')->assertJsonPath('meta.total', 1);

        $this->actingAs($user)->postJson('/api/v1/mail/messages/restore', ['ids' => [$message->id]])->assertOk();
        $this->assertNull($message->fresh()->trashed_at);
    }

    public function test_a_user_cannot_trash_another_users_messages(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $message = $this->message($owner, $account);

        $this->actingAs($other)->postJson('/api/v1/mail/messages/trash', ['ids' => [$message->id]])
            ->assertOk()->assertJson(['updated' => 0]);
        $this->assertNull($message->fresh()->trashed_at);
    }

    public function test_owner_can_store_a_sealed_envelope_and_it_is_returned_in_the_index(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->message($user, $account);

        $this->actingAs($user)->postJson("/api/v1/mail/messages/{$message->id}/envelope", [
            'envelope' => 'BASE64BLOB', 'envelope_key' => '{"suite":1}',
        ])->assertOk()->assertJson(['stored' => true]);

        $this->actingAs($user)->getJson('/api/v1/mail/messages')
            ->assertJsonPath('data.0.envelope', 'BASE64BLOB')
            ->assertJsonPath('data.0.envelope_key', '{"suite":1}');
    }

    public function test_a_user_cannot_store_an_envelope_on_another_users_message(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $message = $this->message($owner, $account);

        $this->actingAs($other)->postJson("/api/v1/mail/messages/{$message->id}/envelope", [
            'envelope' => 'x', 'envelope_key' => 'y',
        ])->assertNotFound();
    }
}
