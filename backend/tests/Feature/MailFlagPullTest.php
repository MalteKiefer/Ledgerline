<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\Mail\PullMailFlags;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\MailSyncState;
use App\Models\User;
use App\Support\Mail\ImapFlagReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Flags set elsewhere find their way back into the archive.
 */
class MailFlagPullTest extends TestCase
{
    use RefreshDatabase;

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
            'size' => 1,
            'seen' => false,
            'flagged' => false,
            'created_at' => now(),
        ], $overrides))->save();

        return $m;
    }

    /** @param array<int, array{seen:bool, flagged:bool}> $flags */
    private function reader(array $flags, ?int $uidvalidity = 1690000000, ?int $modseq = 90): ImapFlagReader
    {
        return new class($flags, $uidvalidity, $modseq) extends ImapFlagReader
        {
            public ?int $sawSince = null;

            public function __construct(private array $flags, private ?int $uidvalidity, private ?int $modseq) {}

            public function read(MailAccount $account, string $folder, ?int $sinceModseq, string $password): array
            {
                $this->sawSince = $sinceModseq;

                return ['uidvalidity' => $this->uidvalidity, 'modseq' => $this->modseq, 'flags' => $this->flags];
            }
        };
    }

    public function test_a_mail_read_on_the_phone_stops_being_unread_here(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->message($user, $account);

        (new PullMailFlags($account->id, 'INBOX'))->handle($this->reader([42 => ['seen' => true, 'flagged' => true]]));

        $message->refresh();
        $this->assertTrue((bool) $message->seen);
        $this->assertTrue((bool) $message->flagged);
        $this->assertNotNull($message->seen_at);

        // The cursor is kept so the next look only asks for what changed.
        $state = MailSyncState::query()->where('account_id', $account->id)->where('folder', 'INBOX')->first();
        $this->assertSame(90, $state?->highmodseq);
    }

    public function test_the_next_pull_only_asks_for_what_changed(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->message($user, $account);
        MailSyncState::query()->insert([
            'account_id' => $account->id, 'folder' => 'INBOX',
            'uidvalidity' => 1690000000, 'highest_uid' => 0, 'highmodseq' => 55, 'updated_at' => now(),
        ]);

        $reader = $this->reader([]);
        (new PullMailFlags($account->id, 'INBOX'))->handle($reader);

        $this->assertSame(55, $reader->sawSince);
    }

    public function test_a_renumbered_folder_drops_the_cursor_instead_of_applying_flags(): void
    {
        // Every UID we hold for that folder now names a different message, so
        // applying these flags would mark the wrong mail read.
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->message($user, $account);
        MailSyncState::query()->insert([
            'account_id' => $account->id, 'folder' => 'INBOX',
            'uidvalidity' => 1690000000, 'highest_uid' => 0, 'highmodseq' => 55, 'updated_at' => now(),
        ]);

        (new PullMailFlags($account->id, 'INBOX'))
            ->handle($this->reader([42 => ['seen' => true, 'flagged' => true]], uidvalidity: 1700000000));

        $this->assertFalse((bool) $message->refresh()->seen);
        $state = MailSyncState::query()->where('account_id', $account->id)->where('folder', 'INBOX')->first();
        $this->assertNull($state?->highmodseq);
    }

    public function test_flags_are_not_applied_across_accounts(): void
    {
        $mine = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $mine->id]);
        $other = MailAccount::factory()->create(['user_id' => User::factory()->create()->id]);
        $theirs = $this->message(User::factory()->create(), $other);

        (new PullMailFlags($account->id, 'INBOX'))->handle($this->reader([42 => ['seen' => true, 'flagged' => false]]));

        $this->assertFalse((bool) $theirs->refresh()->seen);
    }
}
