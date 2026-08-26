<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\Mail\WriteBackMailMove;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\Mail\ImapMover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Throwing mail away here reaches the mailbox — and never costs the archive.
 */
class MailTrashWriteBackTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, array $overrides = []): MailAccount
    {
        return MailAccount::factory()->create(array_merge(['user_id' => $user->id], $overrides));
    }

    private function message(User $user, MailAccount $account, array $overrides = []): MailMessage
    {
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'uid' => 42,
            'uidvalidity' => 1690000000,
            'content_hash' => hash('sha256', (string) Str::uuid()),
            'size' => 10,
            'created_at' => now(),
        ], $overrides))->save();

        return $m;
    }

    public function test_trashing_moves_it_on_the_server_and_keeps_the_archived_copy(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user, ['trash_folder' => 'Deleted Items']);
        $message = $this->message($user, $account);

        $this->actingAs($user)
            ->postJson('/mail/messages/trash', ['ids' => [$message->id]])
            ->assertOk();

        Queue::assertPushed(WriteBackMailMove::class);
        $message->refresh();
        // The archived row survives its own deletion — that is the promise.
        $this->assertNotNull($message->trashed_at);
        // And it remembers where it came from, so restoring is not a guess.
        $this->assertSame('INBOX', $message->restore_folder);
    }

    public function test_restoring_puts_it_back_where_it_came_from(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user);
        $message = $this->message($user, $account, [
            'folder' => 'Trash',
            'restore_folder' => 'Archive',
            'trashed_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/mail/messages/restore', ['ids' => [$message->id]])
            ->assertOk();

        // Not INBOX: a message that was archived belongs back in Archive.
        Queue::assertPushed(WriteBackMailMove::class, fn (WriteBackMailMove $job): bool => $job->target === 'Archive');
    }

    public function test_an_account_with_server_deletes_off_only_hides_it_here(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user, ['write_back_deletes' => false]);
        $message = $this->message($user, $account);

        $this->actingAs($user)
            ->postJson('/mail/messages/trash', ['ids' => [$message->id]])
            ->assertOk();

        Queue::assertNothingPushed();
        $this->assertNotNull($message->refresh()->trashed_at);
    }

    public function test_a_move_without_uidplus_drops_the_handle_rather_than_keeping_a_stale_one(): void
    {
        // The message is in a different folder now and its old number names
        // some other message there. Keeping it would aim the next write-back at
        // a stranger's mail.
        $user = User::factory()->create();
        $account = $this->account($user);
        $message = $this->message($user, $account);

        $mover = new class extends ImapMover
        {
            public function move(MailAccount $account, string $folder, array $uids, string $target, int $uidvalidity, string $password): array
            {
                return ['moved' => count($uids), 'uidvalidity' => null, 'map' => []];
            }
        };

        (new WriteBackMailMove($account->id, 'INBOX', 1690000000, [42], 'Trash', [$message->id]))->handle($mover);

        $message->refresh();
        $this->assertSame('Trash', $message->folder);
        $this->assertNull($message->uid);
        $this->assertNull($message->uidvalidity);
    }

    public function test_a_move_with_uidplus_keeps_the_message_addressable(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $message = $this->message($user, $account);

        $mover = new class extends ImapMover
        {
            public function move(MailAccount $account, string $folder, array $uids, string $target, int $uidvalidity, string $password): array
            {
                return ['moved' => 1, 'uidvalidity' => 1700000000, 'map' => [42 => 7]];
            }
        };

        (new WriteBackMailMove($account->id, 'INBOX', 1690000000, [42], 'Trash', [$message->id]))->handle($mover);

        $message->refresh();
        $this->assertSame(7, $message->uid);
        $this->assertSame(1700000000, $message->uidvalidity);
    }
}
