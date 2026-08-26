<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\Mail\ImapFolders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Managing the mailbox's own folders.
 */
class MailFolderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user): MailAccount
    {
        return MailAccount::factory()->create(['user_id' => $user->id]);
    }

    public function test_renaming_repoints_the_archived_mail(): void
    {
        // Otherwise the rows would name a folder the server no longer has, and
        // every later write-back would aim at nothing.
        $user = User::factory()->create();
        $account = $this->account($user);
        $message = new MailMessage;
        $message->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'folder' => 'Old',
            'content_hash' => hash('sha256', 'x'),
            'size' => 1,
            'created_at' => now(),
        ])->save();

        $this->swap(ImapFolders::class, new class extends ImapFolders
        {
            public function rename(MailAccount $account, string $from, string $to, string $password): void {}
        });

        $this->actingAs($user)
            ->postJson('/mail/server-folders/rename', ['account_id' => $account->id, 'from' => 'Old', 'to' => 'New'])
            ->assertOk();

        $this->assertSame('New', $message->refresh()->folder);
    }

    public function test_a_folder_that_still_holds_mail_is_not_deleted(): void
    {
        // IMAP's DELETE takes the messages with it and there is no undo on the
        // server, so this refusal is the whole point of the endpoint.
        $user = User::factory()->create();
        $account = $this->account($user);

        $this->swap(ImapFolders::class, new class extends ImapFolders
        {
            public function delete(MailAccount $account, string $name, string $password): void
            {
                throw new RuntimeException('mail folders: '.$name.' still holds 12 message(s)');
            }
        });

        $this->actingAs($user)
            ->postJson('/mail/server-folders/delete', ['account_id' => $account->id, 'name' => 'Projects'])
            ->assertStatus(502)
            ->assertJsonPath('ok', false);
    }

    public function test_another_users_mailbox_is_never_contacted(): void
    {
        // The status is not the interesting part — a failed rule on a web route
        // redirects. What matters is that their server was never spoken to.
        $mine = User::factory()->create();
        $theirs = $this->account(User::factory()->create());

        $spy = new class extends ImapFolders
        {
            public int $calls = 0;

            public function list(MailAccount $account, string $password): array
            {
                $this->calls++;

                return [];
            }
        };
        $this->swap(ImapFolders::class, $spy);

        $this->actingAs($mine)->getJson('/mail/server-folders?account_id='.$theirs->id);

        $this->assertSame(0, $spy->calls);
    }
}
