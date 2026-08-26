<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\Mail\IngestStatus;
use App\Services\Mail\MaildirIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailIngestTest extends TestCase
{
    use RefreshDatabase;

    private string $maildir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->maildir = sys_get_temp_dir().'/mailtest-'.bin2hex(random_bytes(6));
        @mkdir($this->maildir.'/cur', 0700, true);
        @mkdir($this->maildir.'/new', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->maildir);
        parent::tearDown();
    }

    private function account(array $overrides = []): MailAccount
    {
        return MailAccount::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
        ], $overrides));
    }

    private function drop(string $raw, string $sub = 'cur', string $name = '1700000000.M1,U=42:2,S'): string
    {
        $path = $this->maildir.'/'.$sub.'/'.$name;
        file_put_contents($path, $raw);

        return $path;
    }

    private function sampleEml(string $subject = 'Hello invoice', string $body = 'This is the body about an invoice payment.'): string
    {
        return implode("\r\n", [
            'From: Alice Example <alice@example.com>',
            'To: Bob <bob@example.com>',
            'Cc: carol@example.com',
            'Subject: '.$subject,
            'Message-ID: <abc123@example.com>',
            'Date: Wed, 05 Aug 2026 10:00:00 +0000',
            'Authentication-Results: mx.test; spf=pass smtp.mailfrom=example.com; dkim=pass; dmarc=pass',
            'Content-Type: text/plain; charset=utf-8',
            '',
            $body,
            '',
        ]);
    }

    public function test_ingest_stores_row_blob_and_denormalised_envelope(): void
    {
        $account = $this->account();
        $path = $this->drop($this->sampleEml());

        $result = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertSame(IngestStatus::Stored, $result->status);
        $this->assertSame('42', $result->uid);

        $msg = MailMessage::query()->where('user_id', $account->user_id)->firstOrFail();
        $this->assertSame('alice@example.com', $msg->from_email);
        $this->assertSame('Alice Example', $msg->from_name);
        $this->assertSame('Hello invoice', $msg->subject);
        $this->assertSame('INBOX', $msg->folder);
        $this->assertTrue($msg->seen); // filename flag S
        $this->assertNotNull($msg->text_body);
        $this->assertStringContainsString('invoice', (string) $msg->text_body);
        $this->assertStringContainsString('invoice', (string) $msg->search_text);
        $this->assertStringContainsString('alice@example.com', (string) $msg->search_text);
        $this->assertSame('pass', $msg->spf);
        $this->assertSame('pass', $msg->dkim);
        $this->assertSame('pass', $msg->dmarc);
        // zbateson normalises the Message-Id (strips the angle brackets).
        $this->assertSame('abc123@example.com', $msg->message_id);
        $this->assertIsArray($msg->to_json);
        $this->assertSame('bob@example.com', $msg->to_json[0]['email']);

        // Ledger + plaintext blob on disk; the message id doubles as the blob key.
        $this->assertDatabaseHas('mail_blobs', ['blob' => $msg->id, 'user_id' => $account->user_id]);
        Storage::disk(config('files.disk'))->assertExists('mail/'.$msg->id);

        // The Maildir source is shredded once durably archived.
        $this->assertFileDoesNotExist($path);
    }

    public function test_origin_uid_and_uidvalidity_are_recorded_together(): void
    {
        // The archive can only carry a change back to the mailbox if it knows
        // which message it is: a UID, and the generation of the folder that UID
        // belongs to. mbsync puts the first in the filename, the second in a
        // dotfile beside cur/.
        file_put_contents($this->maildir.'/.uidvalidity', "1690000000\n5\n");
        $account = $this->account();

        (new MaildirIngestor)->ingestFile($account, 'INBOX', $this->drop($this->sampleEml()));

        $message = MailMessage::query()->where('user_id', $account->user_id)->firstOrFail();
        $this->assertSame(42, $message->uid);
        $this->assertSame(1690000000, $message->uidvalidity);
    }

    public function test_a_message_without_a_folder_generation_records_no_uid_at_all(): void
    {
        // A UID on its own points at nothing: the server hands out fresh ones
        // whenever it renumbers a folder. Half a reference would aim a
        // write-back at whatever message now happens to hold that number, so
        // neither half is kept.
        $account = $this->account();

        (new MaildirIngestor)->ingestFile($account, 'INBOX', $this->drop($this->sampleEml()));

        $message = MailMessage::query()->where('user_id', $account->user_id)->firstOrFail();
        $this->assertNull($message->uidvalidity);
        $this->assertNull($message->uid);
    }

    public function test_duplicate_is_not_stored_twice(): void
    {
        $account = $this->account();
        $ingestor = app(MaildirIngestor::class);

        $ingestor->ingestFile($account, 'INBOX', $this->drop($this->sampleEml(), 'cur', 'a.eml'));
        $second = $ingestor->ingestFile($account, 'INBOX', $this->drop($this->sampleEml(), 'cur', 'b.eml'));

        $this->assertSame(IngestStatus::Duplicate, $second->status);
        $this->assertSame(1, MailMessage::query()->where('user_id', $account->user_id)->count());
    }

    public function test_origin_flagged_spam_is_skipped_when_skip_spam_on(): void
    {
        $account = $this->account(['skip_spam' => true]);
        $raw = "X-Spam-Flag: YES\r\n".$this->sampleEml('Cheap pills');
        $path = $this->drop($raw);

        $result = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertSame(IngestStatus::SkippedSpam, $result->status);
        $this->assertSame(0, MailMessage::query()->where('user_id', $account->user_id)->count());
        $this->assertFileDoesNotExist($path); // local copy dropped; origin untouched
    }

    public function test_spam_is_archived_and_flagged_when_skip_spam_off(): void
    {
        $account = $this->account(['skip_spam' => false]);
        $raw = "X-Spam-Flag: YES\r\n".$this->sampleEml('Cheap pills');

        $result = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $this->drop($raw));

        $this->assertSame(IngestStatus::Stored, $result->status);
        $this->assertTrue(MailMessage::query()->where('user_id', $account->user_id)->firstOrFail()->spam);
    }

    public function test_message_before_backfill_cutoff_is_skipped(): void
    {
        $account = $this->account(['backfill_since' => now()->subDays(2)->toDateString()]);
        $path = $this->drop($this->sampleEml(), 'new', 'old.eml');
        // Arrival = file mtime; set it well before the cutoff.
        touch($path, now()->subDays(10)->getTimestamp());

        $result = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertSame(IngestStatus::SkippedOld, $result->status);
        $this->assertSame(0, MailMessage::query()->where('user_id', $account->user_id)->count());
        $this->assertFileDoesNotExist($path);
    }

    public function test_unreadable_file_is_quarantined_never_dropped(): void
    {
        $account = $this->account();
        // A vanished/unreadable path makes file_get_contents fail (deterministic
        // across platforms, unlike reading a directory).
        $path = $this->maildir.'/cur/vanished.eml';

        $result = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertSame(IngestStatus::Quarantined, $result->status);
        $this->assertSame(0, MailMessage::query()->where('user_id', $account->user_id)->count());
        // Preserved (moved aside), never silently lost.
        $this->assertDirectoryExists($this->maildir.'/cur/.quarantine');
    }

    public function test_oversized_message_is_quarantined_not_read_into_memory(): void
    {
        config(['mail_archive.max_message_bytes' => 1024]); // 1 KiB cap for the test
        $account = $this->account();
        // A 2 KiB file — over the cap. It must be quarantined WITHOUT being read
        // (the OOM guard checks filesize first), never stored, never thrown.
        $path = $this->drop(str_repeat('A', 2048), 'cur', 'huge.eml');

        $result = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertSame(IngestStatus::Quarantined, $result->status);
        $this->assertSame(0, MailMessage::query()->where('user_id', $account->user_id)->count());
        // Preserved (moved aside), never silently lost or retried into OOM.
        $this->assertDirectoryExists($this->maildir.'/cur/.quarantine');
        $this->assertFileDoesNotExist($path);

        // A within-cap message still archives normally.
        $ok = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $this->drop($this->sampleEml(), 'cur', 'ok.eml'));
        $this->assertSame(IngestStatus::Stored, $ok->status);
    }

    public function test_html_body_is_server_sanitised(): void
    {
        $account = $this->account();
        $raw = implode("\r\n", [
            'From: a@example.com',
            'Subject: HTML',
            'Content-Type: text/html; charset=utf-8',
            '',
            '<p>Hi</p><script>alert(1)</script><img src="http://tracker.example/px.gif"><a href="https://ok.example">link</a>',
            '',
        ]);

        app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $this->drop($raw));

        $html = (string) MailMessage::query()->where('user_id', $account->user_id)->firstOrFail()->html_sanitized;
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('tracker.example', $html); // remote img neutralised
        $this->assertStringContainsString('Hi', $html);
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
