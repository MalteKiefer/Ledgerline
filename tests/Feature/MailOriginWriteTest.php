<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\Mail\IngestMailChunk;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\Mail\MaildirIngestor;
use App\Support\Mail\ImapAppender;
use App\Support\Mail\ImapDeleter;
use App\Support\Mail\ImapStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * A scripted in-memory IMAP server: it answers the client's tagged commands
 * with the right tagged OKs (and a `+` continuation for APPEND, a `* SEARCH`
 * line for UID SEARCH) so the raw-IMAP protocol logic is exercised end-to-end
 * without a real socket. Records the full write transcript for assertions.
 */
class FakeImapStream implements ImapStream
{
    /** @var list<string> */
    public array $written = [];

    /** @var list<string> */
    private array $out = [];

    private ?string $pendingAppendTag = null;

    public bool $cryptoEnabled = false;

    /** @param list<string> $searchUids UIDs returned for UID SEARCH. */
    public function __construct(private array $searchUids = [])
    {
        $this->out[] = '* OK IMAP ready';
    }

    public function write(string $data): void
    {
        $this->written[] = $data;

        // A pending APPEND literal payload → complete the APPEND.
        if ($this->pendingAppendTag !== null) {
            $this->out[] = $this->pendingAppendTag.' OK APPEND completed';
            $this->pendingAppendTag = null;

            return;
        }

        if (preg_match('/^([a-z]\d{4}) (.*)\r\n$/', $data, $m) !== 1) {
            return; // e.g. the untagged LOGOUT — no response consumed.
        }
        [$tag, $command] = [$m[1], $m[2]];
        $verb = strtoupper(strtok($command, ' ') ?: '');

        if ($verb === 'APPEND') {
            $this->out[] = '+ Ready for literal data';
            $this->pendingAppendTag = $tag;

            return;
        }
        if (str_starts_with(strtoupper($command), 'UID SEARCH')) {
            $this->out[] = '* SEARCH '.implode(' ', $this->searchUids);
            $this->out[] = $tag.' OK SEARCH completed';

            return;
        }

        $this->out[] = $tag.' OK '.$verb.' completed';
    }

    public function readLine(): string
    {
        if ($this->out === []) {
            throw new RuntimeException('fake IMAP: nothing to read');
        }

        return array_shift($this->out);
    }

    public function enableCrypto(): bool
    {
        $this->cryptoEnabled = true;

        return true;
    }

    public function close(): void {}
}

class MailOriginWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function account(array $overrides = []): MailAccount
    {
        return MailAccount::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'host' => 'mail.example.invalid',   // unresolvable → SSRF guard allows
            'encryption' => 'ssl',
            'username' => 'user@example.com',
            'password' => 'imap-secret',
        ], $overrides));
    }

    private function message(MailAccount $account, array $attrs = []): MailMessage
    {
        $id = (string) Str::uuid();
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => $id,
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => hash('sha256', $id),
            'size' => 10,
            'message_id' => 'msg-'.$id.'@example.com',
            'seen' => true,
            'created_at' => now(),
        ], $attrs))->save();

        return $m;
    }

    public function test_appender_appends_over_a_fake_stream(): void
    {
        $account = $this->account();
        $fake = new FakeImapStream;
        $appender = new class($fake) extends ImapAppender
        {
            public function __construct(public ImapStream $fakeStream) {}

            protected function connect(MailAccount $account): ImapStream
            {
                return $this->fakeStream;
            }
        };

        $appender->append($account, 'INBOX', "Subject: Hi\r\n\r\nbody", 'imap-secret', seen: true);

        $joined = implode('', $fake->written);
        $this->assertStringContainsString('APPEND "INBOX" (\\Seen) {', $joined);
        $this->assertStringContainsString('Subject: Hi', $joined);
        $this->assertStringContainsString('LOGOUT', $joined);
    }

    public function test_deleter_deletes_by_message_id_over_a_fake_stream(): void
    {
        $account = $this->account();
        $fake = new FakeImapStream(['5', '6']);
        $deleter = new class($fake) extends ImapDeleter
        {
            public function __construct(public ImapStream $fakeStream) {}

            protected function connect(MailAccount $account): ImapStream
            {
                return $this->fakeStream;
            }
        };

        $count = $deleter->delete($account, 'INBOX', '<abc@example.com>', 'imap-secret');

        $this->assertSame(2, $count);
        $joined = implode('', $fake->written);
        $this->assertStringContainsString('UID SEARCH HEADER Message-Id', $joined);
        $this->assertStringContainsString('UID STORE 5,6 +FLAGS (\\Deleted)', $joined);
    }

    public function test_ssrf_guard_rejects_a_blocked_host(): void
    {
        $account = $this->account(['host' => '169.254.169.254']); // link-local / metadata
        $this->expectException(RuntimeException::class);
        (new ImapAppender)->append($account, 'INBOX', "a\r\n\r\nb", 'pw');
    }

    public function test_pushback_controller_appends_and_sets_no_store(): void
    {
        $account = $this->account();
        $msg = $this->message($account);
        Storage::disk(config('files.disk'))->put('mail/'.$msg->id, "Subject: X\r\n\r\nbody");

        $spy = new class extends ImapAppender
        {
            public bool $called = false;

            public function append(MailAccount $account, string $folder, string $rawMessage, string $password, bool $seen = false): void
            {
                $this->called = true;
            }
        };
        $this->app->instance(ImapAppender::class, $spy);

        $res = $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.messages.pushback', $msg->id), [])
            ->assertOk()
            ->assertJson(['ok' => true]);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertTrue($spy->called);

        // Foreign user → 404.
        $this->actingAs(User::factory()->create())
            ->postJson(route('mail.messages.pushback', $msg->id), [])->assertNotFound();
    }

    public function test_delete_origin_controller_uses_stored_message_id(): void
    {
        $account = $this->account();
        $msg = $this->message($account);

        $spy = new class extends ImapDeleter
        {
            public ?string $seenMessageId = null;

            public function delete(MailAccount $account, string $folder, string $messageId, string $password): int
            {
                $this->seenMessageId = $messageId;

                return 1;
            }
        };
        $this->app->instance(ImapDeleter::class, $spy);

        $res = $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.messages.delete-origin', $msg->id), [])
            ->assertOk()
            ->assertJson(['ok' => true, 'expunged' => 1]);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertSame($msg->message_id, $spy->seenMessageId);

        // A message with no Message-Id → 422.
        $noId = $this->message($account, ['message_id' => null]);
        $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.messages.delete-origin', $noId->id), [])
            ->assertStatus(422);
    }

    public function test_delete_after_import_removes_origin_uids(): void
    {
        $account = $this->account(['delete_after_import' => true]);

        $maildir = sys_get_temp_dir().'/maildel-'.bin2hex(random_bytes(6));
        @mkdir($maildir.'/cur', 0700, true);
        $path = $maildir.'/cur/1700000000.M1,U=77:2,S';
        file_put_contents($path, "From: a@b.c\r\nSubject: keep\r\n\r\nbody\r\n");

        $spy = new class extends ImapDeleter
        {
            /** @var list<string> */
            public array $uids = [];

            public function deleteUids(MailAccount $account, string $folder, array $uids, string $password): int
            {
                $this->uids = $uids;

                return count($uids);
            }
        };

        (new IngestMailChunk($account->id, 'INBOX', [$path]))
            ->handle(app(MaildirIngestor::class), $spy);

        $this->assertSame(['77'], $spy->uids);
        $this->assertSame(1, MailMessage::query()->where('user_id', $account->user_id)->count());

        @unlink($path);
        @rmdir($maildir.'/cur');
        @rmdir($maildir);
    }
}
