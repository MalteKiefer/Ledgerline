<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailReaderTest extends TestCase
{
    use RefreshDatabase;

    private ?int $accountId = null;

    private function acct(): int
    {
        return $this->accountId ??= MailAccount::create([
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me', 'password' => 'secret',
        ])->id;
    }

    private function fakeReader(): object
    {
        $fake = new class implements ImapReader
        {
            public array $calls = [];

            public function listFolders(ImapCredentials $c): array
            {
                return [['name' => 'INBOX', 'path' => 'INBOX', 'delimiter' => '/', 'selectable' => true, 'total' => 1, 'unseen' => 1]];
            }

            public function createFolder(ImapCredentials $c, string $path): void
            {
                $this->calls[] = ['createFolder', $path];
            }

            public function emptyFolder(ImapCredentials $c, string $path): void
            {
                $this->calls[] = ['emptyFolder', $path];
            }

            public function listMessages(ImapCredentials $c, string $folder, int $page, int $perPage): array
            {
                $this->calls[] = ['list', $folder, $page];

                return ['total' => 1, 'page' => $page, 'perPage' => $perPage, 'uidValidity' => 42, 'messages' => [
                    ['uid' => 5, 'subject' => 'Hi', 'from' => ['name' => 'A', 'email' => 'a@x.test'], 'date' => null, 'seen' => false, 'flagged' => false, 'answered' => false, 'hasAttachments' => false],
                ]];
            }

            public function getMessage(ImapCredentials $c, string $folder, int $uid, bool $markSeen): array
            {
                $this->calls[] = ['get', $uid, $markSeen];

                return ['uid' => $uid, 'subject' => 'Hi', 'from' => null, 'to' => [], 'cc' => [], 'date' => null, 'seen' => true, 'html' => null, 'text' => 'body', 'attachments' => [], 'uidValidity' => 42];
            }

            public function getAttachment(ImapCredentials $c, string $folder, int $uid, int $attachmentId): array
            {
                return ['name' => 'file.txt', 'mime' => 'text/plain', 'content' => 'bytes'];
            }

            public function actOnMessages(ImapCredentials $c, string $folder, array $uids, string $action, ?string $target): array
            {
                $this->calls[] = ['act', $uids, $action, $target];

                return ['count' => count($uids)];
            }

            public function transferMessages(ImapCredentials $source, string $folder, array $uids, ImapCredentials $target, string $targetFolder): array
            {
                $this->calls[] = ['transfer', $uids, $target->host, $targetFolder];

                return ['count' => count($uids)];
            }
        };

        $this->app->instance(ImapReader::class, $fake);

        return $fake;
    }

    private function creds(array $extra = []): array
    {
        return array_merge(['account_id' => $this->acct()], $extra);
    }

    public function test_guests_are_blocked(): void
    {
        $this->post(route('mail.messages'), $this->creds(['folder' => 'INBOX']))->assertRedirect(route('login'));
    }

    public function test_folders_are_listed(): void
    {
        $this->signIn();
        $this->fakeReader();

        $this->postJson(route('mail.folders'), $this->creds())
            ->assertOk()
            ->assertJsonPath('folders.0.path', 'INBOX');
    }

    public function test_a_folder_name_with_control_characters_is_rejected(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $this->from(route('mail.index'))
            ->post(route('mail.messages'), $this->creds(['folder' => "INBOX\r\nX LOGOUT", 'page' => 1]))
            ->assertSessionHasErrors('folder');

        // The reader was never touched with the smuggled command.
        $this->assertSame([], $fake->calls);
    }

    public function test_create_and_empty_folder(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $this->postJson(route('mail.folder.create'), $this->creds(['folder' => 'Archive']))->assertOk();
        $this->postJson(route('mail.folder.empty'), $this->creds(['folder' => 'Trash']))->assertOk();

        $this->assertContains(['createFolder', 'Archive'], $fake->calls);
        $this->assertContains(['emptyFolder', 'Trash'], $fake->calls);
    }

    public function test_list_returns_messages(): void
    {
        $this->signIn();
        $this->fakeReader();

        $this->postJson(route('mail.messages'), $this->creds(['folder' => 'INBOX', 'page' => 1]))
            ->assertOk()
            ->assertJson(['total' => 1])
            ->assertJsonPath('messages.0.uid', 5);
    }

    public function test_open_marks_seen_and_returns_body(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $this->postJson(route('mail.message'), $this->creds(['folder' => 'INBOX', 'uid' => 5]))
            ->assertOk()
            ->assertJson(['uid' => 5, 'text' => 'body']);

        $this->assertSame(['get', 5, true], $fake->calls[0]);
    }

    public function test_attachment_is_streamed_as_download(): void
    {
        $this->signIn();
        $this->fakeReader();

        $res = $this->postJson(route('mail.message.attachment'), $this->creds(['folder' => 'INBOX', 'uid' => 5, 'attachment' => 0]));
        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertSame('bytes', $res->getContent());
    }

    public function test_action_move_requires_a_target(): void
    {
        $this->signIn();
        $this->fakeReader();

        $this->from(route('mail.index'))->post(route('mail.message.action'), $this->creds(['folder' => 'INBOX', 'uids' => [5], 'action' => 'move']))
            ->assertRedirect()->assertSessionHasErrors('target');
    }

    public function test_action_delete_calls_reader_permanently(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $this->postJson(route('mail.message.action'), $this->creds(['folder' => 'INBOX', 'uids' => [5, 6], 'action' => 'delete']))
            ->assertOk()
            ->assertJson(['count' => 2]);

        $this->assertSame(['act', [5, 6], 'delete', null], $fake->calls[0]);
    }

    public function test_transfer_passes_both_accounts(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $target = MailAccount::create([
            'name' => 'Other', 'host' => 'imap.other.test', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me2', 'password' => 'secret',
        ]);

        $this->postJson(route('mail.message.transfer'), $this->creds([
            'folder' => 'INBOX', 'uids' => [5], 'target_folder' => 'INBOX',
            'target_account_id' => $target->id,
        ]))->assertOk();

        $this->assertSame(['transfer', [5], 'imap.other.test', 'INBOX'], $fake->calls[0]);
    }

    public function test_a_connection_failure_is_generic_422(): void
    {
        $this->signIn();
        $this->app->instance(ImapReader::class, new class implements ImapReader
        {
            public function listFolders(ImapCredentials $c): array
            {
                throw new \RuntimeException('boom');
            }

            public function createFolder(ImapCredentials $c, string $path): void {}

            public function emptyFolder(ImapCredentials $c, string $path): void {}

            public function listMessages(ImapCredentials $c, string $folder, int $page, int $perPage): array
            {
                throw new \RuntimeException('boom');
            }

            public function getMessage(ImapCredentials $c, string $folder, int $uid, bool $markSeen): array
            {
                return [];
            }

            public function getAttachment(ImapCredentials $c, string $folder, int $uid, int $attachmentId): array
            {
                return [];
            }

            public function actOnMessages(ImapCredentials $c, string $folder, array $uids, string $action, ?string $target): array
            {
                return ['count' => 0];
            }

            public function transferMessages(ImapCredentials $source, string $folder, array $uids, ImapCredentials $target, string $targetFolder): array
            {
                return ['count' => 0];
            }
        });

        $this->postJson(route('mail.messages'), $this->creds(['folder' => 'INBOX']))
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'detail'])
            // The class and the underlying error message are surfaced so a
            // single-tenant operator can diagnose failures from the browser.
            ->assertJsonPath('detail', 'RuntimeException: boom');
    }
}
