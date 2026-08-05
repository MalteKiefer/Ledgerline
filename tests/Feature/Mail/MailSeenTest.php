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
 * POST /mail/messages/seen — bulk mark archived mail read/unread. Metadata only,
 * owner-scoped (ignores ids the caller does not own), never touches content or
 * the origin mailbox.
 */
class MailSeenTest extends TestCase
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

    public function test_marks_owned_messages_read_and_unread(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $a = $this->message($user, $account, seen: false);
        $b = $this->message($user, $account, seen: false);

        $this->actingAs($user)
            ->postJson('/api/v1/mail/messages/seen', ['ids' => [$a->id, $b->id], 'seen' => true])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertTrue($a->fresh()->seen);
        $this->assertTrue($b->fresh()->seen);

        $this->actingAs($user)
            ->postJson('/api/v1/mail/messages/seen', ['ids' => [$a->id], 'seen' => false])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertFalse($a->fresh()->seen);
        $this->assertTrue($b->fresh()->seen);
    }

    public function test_cannot_change_another_users_message(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $message = $this->message($owner, null, seen: false);

        $this->actingAs($other)
            ->postJson('/api/v1/mail/messages/seen', ['ids' => [$message->id], 'seen' => true])
            ->assertOk()
            ->assertJsonPath('updated', 0);

        $this->assertFalse($message->fresh()->seen);
    }
}
