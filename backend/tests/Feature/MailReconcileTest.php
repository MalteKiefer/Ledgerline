<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\Mail\ImapFolders;
use App\Support\Mail\ImapMessageIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Matching the archive against the mailbox: attaching origin UIDs to mail
 * archived before they were recorded, and marking what the server no longer
 * has — without ever removing the archived copy.
 */
class MailReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function account(): MailAccount
    {
        return MailAccount::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    private function message(MailAccount $account, string $messageId, array $overrides = []): MailMessage
    {
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'message_id' => $messageId,
            'content_hash' => hash('sha256', $messageId),
            'size' => 1,
            'created_at' => now(),
        ], $overrides))->save();

        return $m;
    }

    /** @param array<string, array<string, int>> $byFolder folder => (message-id => uid) */
    private function fake(array $byFolder, array $unreadable = []): void
    {
        $this->swap(ImapFolders::class, new class(array_keys($byFolder)) extends ImapFolders
        {
            /** @param list<string> $names */
            public function __construct(private array $names) {}

            public function list(MailAccount $account, string $password): array
            {
                return array_map(fn (string $n): array => ['name' => $n, 'delimiter' => '.', 'selectable' => true], $this->names);
            }
        });

        $this->swap(ImapMessageIndex::class, new class($byFolder, $unreadable) extends ImapMessageIndex
        {
            public function __construct(private array $byFolder, private array $unreadable) {}

            public function index(MailAccount $account, string $folder, string $password): array
            {
                if (in_array($folder, $this->unreadable, true)) {
                    throw new RuntimeException('no access');
                }

                return ['uidvalidity' => 1690000000, 'ids' => $this->byFolder[$folder] ?? []];
            }
        });
    }

    public function test_mail_archived_before_uids_were_recorded_gets_linked(): void
    {
        $account = $this->account();
        $old = $this->message($account, '<a@example.com>');

        $this->fake(['INBOX' => ['a@example.com' => 77]]);
        $this->artisan('mail:reconcile')->assertSuccessful();

        $old->refresh();
        $this->assertSame(77, $old->uid);
        $this->assertSame(1690000000, $old->uidvalidity);
    }

    public function test_a_message_the_server_no_longer_has_is_marked_and_kept(): void
    {
        $account = $this->account();
        $gone = $this->message($account, '<gone@example.com>', ['uid' => 5, 'uidvalidity' => 1690000000]);

        $this->fake(['INBOX' => []]);
        $this->artisan('mail:reconcile')->assertSuccessful();

        $gone->refresh();
        $this->assertNotNull($gone->removed_from_server_at);
        // Marked, not removed — the archived copy is the point of the archive.
        $this->assertDatabaseHas('mail_messages', ['id' => $gone->id]);
        // And its UID is cleared, because it names nothing now.
        $this->assertNull($gone->uid);
    }

    public function test_a_message_moved_in_another_client_is_relocated_not_declared_gone(): void
    {
        $account = $this->account();
        $moved = $this->message($account, '<m@example.com>');

        $this->fake(['INBOX' => [], 'Archive' => ['m@example.com' => 3]]);
        $this->artisan('mail:reconcile')->assertSuccessful();

        $moved->refresh();
        $this->assertSame('Archive', $moved->folder);
        $this->assertSame(3, $moved->uid);
        $this->assertNull($moved->removed_from_server_at);
    }

    public function test_a_folder_that_cannot_be_read_never_makes_its_mail_look_deleted(): void
    {
        // Not being able to look is not the same as it being gone, and this is
        // the failure that would wrongly mark a whole mailbox.
        $account = $this->account();
        $kept = $this->message($account, '<k@example.com>', ['folder' => 'Locked']);

        $this->fake(['Locked' => []], unreadable: ['Locked']);
        $this->artisan('mail:reconcile')->assertSuccessful();

        $this->assertNull($kept->refresh()->removed_from_server_at);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $account = $this->account();
        $old = $this->message($account, '<a@example.com>');

        $this->fake(['INBOX' => ['a@example.com' => 77]]);
        $this->artisan('mail:reconcile', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($old->refresh()->uid);
    }

    public function test_a_message_without_a_message_id_is_left_alone(): void
    {
        // There is nothing to match it on, so neither linking nor marking it
        // would be based on anything.
        $account = $this->account();
        $anon = $this->message($account, '', ['message_id' => null]);

        $this->fake(['INBOX' => []]);
        $this->artisan('mail:reconcile')->assertSuccessful();

        $anon->refresh();
        $this->assertNull($anon->removed_from_server_at);
        $this->assertNull($anon->uid);
    }
}
