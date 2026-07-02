<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailReaderTest extends TestCase
{
    use RefreshDatabase;

    private function fakeReader(): object
    {
        $fake = new class implements ImapReader
        {
            public array $calls = [];

            public function listMessages(ImapCredentials $c, string $folder, int $page, int $perPage): array
            {
                $this->calls[] = ['list', $folder, $page];

                return ['total' => 1, 'page' => $page, 'perPage' => $perPage, 'messages' => [
                    ['uid' => 5, 'subject' => 'Hi', 'from' => ['name' => 'A', 'email' => 'a@x.test'], 'date' => null, 'seen' => false, 'flagged' => false, 'answered' => false, 'hasAttachments' => false],
                ]];
            }

            public function getMessage(ImapCredentials $c, string $folder, int $uid, bool $markSeen): array
            {
                $this->calls[] = ['get', $uid, $markSeen];

                return ['uid' => $uid, 'subject' => 'Hi', 'from' => null, 'to' => [], 'cc' => [], 'date' => null, 'seen' => true, 'html' => null, 'text' => 'body', 'attachments' => []];
            }

            public function getAttachment(ImapCredentials $c, string $folder, int $uid, int $attachmentId): array
            {
                return ['name' => 'file.txt', 'mime' => 'text/plain', 'content' => 'bytes'];
            }

            public function deleteMessage(ImapCredentials $c, string $folder, int $uid, bool $permanent): array
            {
                $this->calls[] = ['delete', $uid, $permanent];

                return ['deleted' => true, 'trashed' => ! $permanent];
            }

            public function moveMessage(ImapCredentials $c, string $folder, int $uid, string $targetFolder): void
            {
                $this->calls[] = ['move', $uid, $targetFolder];
            }

            public function flagMessage(ImapCredentials $c, string $folder, int $uid, bool $seen): void
            {
                $this->calls[] = ['flag', $uid, $seen];
            }

            public function transferMessage(ImapCredentials $source, string $folder, int $uid, ImapCredentials $target, string $targetFolder): void
            {
                $this->calls[] = ['transfer', $uid, $target->host, $targetFolder];
            }
        };

        $this->app->instance(ImapReader::class, $fake);

        return $fake;
    }

    private function creds(array $extra = []): array
    {
        return array_merge([
            'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'me', 'password' => 'secret', 'validate_cert' => true,
        ], $extra);
    }

    public function test_guests_are_blocked(): void
    {
        $this->post(route('mail.messages'), $this->creds(['folder' => 'INBOX']))->assertRedirect(route('login'));
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

        $this->from(route('mail.index'))->post(route('mail.message.action'), $this->creds(['folder' => 'INBOX', 'uid' => 5, 'action' => 'move']))
            ->assertRedirect()->assertSessionHasErrors('target');
    }

    public function test_action_delete_calls_reader_permanently(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $this->postJson(route('mail.message.action'), $this->creds(['folder' => 'INBOX', 'uid' => 5, 'action' => 'delete']))
            ->assertOk();

        $this->assertSame(['delete', 5, true], $fake->calls[0]);
    }

    public function test_transfer_passes_both_accounts(): void
    {
        $this->signIn();
        $fake = $this->fakeReader();

        $this->postJson(route('mail.message.transfer'), $this->creds([
            'folder' => 'INBOX', 'uid' => 5, 'target_folder' => 'INBOX',
            'target' => $this->creds(['host' => 'imap.other.test']),
        ]))->assertOk();

        $this->assertSame(['transfer', 5, 'imap.other.test', 'INBOX'], $fake->calls[0]);
    }

    public function test_a_connection_failure_is_generic_422(): void
    {
        $this->signIn();
        $this->app->instance(ImapReader::class, new class implements ImapReader
        {
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

            public function deleteMessage(ImapCredentials $c, string $folder, int $uid, bool $permanent): array
            {
                return [];
            }

            public function moveMessage(ImapCredentials $c, string $folder, int $uid, string $targetFolder): void {}

            public function flagMessage(ImapCredentials $c, string $folder, int $uid, bool $seen): void {}

            public function transferMessage(ImapCredentials $source, string $folder, int $uid, ImapCredentials $target, string $targetFolder): void {}
        });

        $this->postJson(route('mail.messages'), $this->creds(['folder' => 'INBOX']))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
