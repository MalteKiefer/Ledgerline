<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\Mail\WriteBackMailFlags;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\Mail\ImapFlagWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Read marks and stars set here reach the mailbox — and only ever the right
 * message.
 */
class MailFlagWriteBackTest extends TestCase
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
            'seen' => false,
            'flagged' => false,
            'created_at' => now(),
        ], $overrides))->save();

        return $m;
    }

    public function test_marking_read_queues_a_write_back_for_the_origin_message(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user);
        $message = $this->message($user, $account);

        $this->actingAs($user)
            ->postJson('/mail/messages/seen', ['ids' => [$message->id], 'seen' => true])
            ->assertOk();

        Queue::assertPushed(WriteBackMailFlags::class);
    }

    public function test_a_message_with_no_origin_reference_is_never_written_back(): void
    {
        // Appended here, or archived before the reference was recorded. There is
        // no message on the server to aim at, and the UID of some other message
        // must not be guessed at.
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user);
        $message = $this->message($user, $account, ['uid' => null, 'uidvalidity' => null]);

        $this->actingAs($user)
            ->postJson('/mail/messages/seen', ['ids' => [$message->id], 'seen' => true])
            ->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_one_batch_per_folder_generation(): void
    {
        // A UID is only meaningful inside one generation of one folder, so two
        // messages that disagree on either cannot travel in the same command.
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user);
        $a = $this->message($user, $account, ['uid' => 1]);
        $b = $this->message($user, $account, ['uid' => 2, 'folder' => 'Archive']);
        $c = $this->message($user, $account, ['uid' => 3, 'uidvalidity' => 1700000000]);

        WriteBackMailFlags::queueFor($user->id, [$a->id, $b->id, $c->id], 'flagged', true);

        Queue::assertPushed(WriteBackMailFlags::class, 3);
    }

    public function test_another_users_message_is_not_written_back(): void
    {
        Queue::fake();
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $message = $this->message($theirs, $this->account($theirs));

        WriteBackMailFlags::queueFor($mine->id, [$message->id], 'seen', true);

        Queue::assertNothingPushed();
    }

    public function test_the_job_does_nothing_when_the_account_has_write_back_off(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, ['write_back_flags' => false]);

        $writer = new class extends ImapFlagWriter
        {
            public int $calls = 0;

            public function store(MailAccount $account, string $folder, array $uids, string $flag, bool $add, int $uidvalidity, string $password): int
            {
                $this->calls++;

                return count($uids);
            }
        };

        (new WriteBackMailFlags($account->id, 'INBOX', 1690000000, [42], 'seen', true))->handle($writer);

        $this->assertSame(0, $writer->calls);
    }
}
