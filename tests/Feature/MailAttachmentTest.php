<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SweepOrphanMailBlobs;
use App\Models\FileEntry;
use App\Models\MailAccount;
use App\Models\MailAttachment;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Mail\MaildirIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private string $maildir;

    /** A 1x1 transparent PNG. */
    private const PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->maildir = sys_get_temp_dir().'/mailatt-'.bin2hex(random_bytes(6));
        @mkdir($this->maildir.'/cur', 0700, true);
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

    /** A multipart/mixed message with an inline cid image + a real text attachment. */
    private function multipartEml(): string
    {
        $boundary = 'BOUNDARY42';
        $lines = [
            'From: Alice <alice@example.com>',
            'To: bob@example.com',
            'Subject: With attachments',
            'Message-ID: <att1@example.com>',
            'Date: Wed, 05 Aug 2026 10:00:00 +0000',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
            '',
            '--'.$boundary,
            'Content-Type: text/html; charset=utf-8',
            '',
            '<html><body><p>Hi</p>'
                .'<img src="cid:logo@example.com">'
                .'<img src="http://tracker.example/px.gif">'
                .'</body></html>',
            '',
            '--'.$boundary,
            'Content-Type: image/png',
            'Content-ID: <logo@example.com>',
            'Content-Disposition: inline; filename="logo.png"',
            'Content-Transfer-Encoding: base64',
            '',
            self::PNG_B64,
            '',
            '--'.$boundary,
            'Content-Type: text/plain; charset=utf-8; name="notes.txt"',
            'Content-Disposition: attachment; filename="notes.txt"',
            '',
            'quarterly numbers here',
            '',
            '--'.$boundary.'--',
            '',
        ];

        return implode("\r\n", $lines);
    }

    private function ingest(MailAccount $account): MailMessage
    {
        $path = $this->maildir.'/cur/1700000000.M1,U=7:2,S';
        file_put_contents($path, $this->multipartEml());
        app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        return MailMessage::query()->where('user_id', $account->user_id)->firstOrFail();
    }

    public function test_ingest_extracts_attachment_rows_and_blobs(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);

        $atts = MailAttachment::query()->where('message_id', $msg->id)->get();
        $this->assertCount(2, $atts);

        $inline = $atts->firstWhere('inline', true);
        $this->assertNotNull($inline);
        $this->assertSame('logo@example.com', $inline->content_id);
        $this->assertSame('image/png', $inline->content_type);
        Storage::disk(config('files.disk'))->assertExists('mail/att/'.$inline->blob);

        $real = $atts->firstWhere('inline', false);
        $this->assertNotNull($real);
        $this->assertSame('notes.txt', $real->filename);

        // Each attachment has an owner-scoped ledger row of kind=attachment.
        $this->assertDatabaseHas('mail_blobs', ['blob' => $inline->blob, 'kind' => 'attachment', 'user_id' => $account->user_id]);
        // Filenames fold into the search index.
        $this->assertStringContainsString('notes.txt', (string) $msg->search_text);
    }

    public function test_attachment_raw_is_owner_scoped_and_sandboxed(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);
        $att = MailAttachment::query()->where('message_id', $msg->id)->where('inline', false)->firstOrFail();

        $res = $this->actingAs(User::findOrFail($account->user_id))
            ->get(route('mail.attachments.raw', $att->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertSame('quarterly numbers here', trim($res->streamedContent()));

        // Foreign user → 404.
        $this->actingAs(User::factory()->create())
            ->get(route('mail.attachments.raw', $att->id))->assertNotFound();
    }

    public function test_body_endpoint_gates_remote_images_and_inlines_cid(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);
        $owner = User::findOrFail($account->user_id);

        // Default (remote off): remote img stripped, cid rewritten to data:.
        $res = $this->actingAs($owner)->get(route('mail.messages.body', $msg->id))->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('img-src data:', (string) $csp);
        $this->assertStringNotContainsString('https:', (string) $csp);
        $body = $res->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('data:image/png;base64,', $body);
        $this->assertStringNotContainsString('tracker.example', $body);

        // Explicit reader toggle remote=1 → remote kept + https: in CSP, even
        // without the (not-yet-settable) mail_load_remote pref.
        $res = $this->actingAs($owner)->get(route('mail.messages.body', [$msg->id, 'remote' => 1]))->assertOk();
        $this->assertStringContainsString('https:', (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('tracker.example', (string) $res->getContent());

        // Pref ON acts as an "always load" default even without the query param.
        UserSetting::for($owner->id)->forceFill(['mail_load_remote' => true])->save();
        $res = $this->actingAs($owner)->get(route('mail.messages.body', $msg->id))->assertOk();
        $this->assertStringContainsString('https:', (string) $res->headers->get('Content-Security-Policy'));
    }

    public function test_show_lists_attachments(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);

        $this->actingAs(User::findOrFail($account->user_id))
            ->getJson(route('mail.messages.show', $msg->id))
            ->assertOk()
            ->assertJsonCount(2, 'message.attachments');
    }

    public function test_save_attachment_to_files_enforces_owner_and_creates_entry(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);
        $att = MailAttachment::query()->where('message_id', $msg->id)->where('inline', false)->firstOrFail();

        $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'files'])
            ->assertOk()
            ->assertJson(['ok' => true, 'target' => 'files']);

        $file = FileEntry::query()->where('user_id', $account->user_id)->firstOrFail();
        $this->assertSame('notes.txt', $file->name);
        Storage::disk(config('files.disk'))->assertExists($file->storage_path);

        // Foreign user → 404.
        $this->actingAs(User::factory()->create())
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'files'])
            ->assertNotFound();
    }

    public function test_save_attachment_to_paperless(): void
    {
        Http::fake(['*' => Http::response('"task-uuid-9"', 200)]);

        $account = $this->account();
        $msg = $this->ingest($account);
        $att = MailAttachment::query()->where('message_id', $msg->id)->where('inline', false)->firstOrFail();

        // Not configured → 422.
        $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'paperless'])
            ->assertStatus(422);

        // Configured (unresolvable host so the real request is faked, not pinned).
        UserSetting::for((int) $account->user_id)->forceFill([
            'paperless_url' => 'http://paperless.invalid',
            'paperless_token' => 'tok',
        ])->save();

        $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'paperless'])
            ->assertOk()
            ->assertJson(['ok' => true, 'target' => 'paperless', 'task' => 'task-uuid-9']);
    }

    public function test_save_rejects_unknown_target(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);
        $att = MailAttachment::query()->where('message_id', $msg->id)->firstOrFail();

        // gallery is not a module in this deployment → validation 422 (asserted
        // on the API twin, whose validation returns JSON 422; the web route
        // redirects back with the error bag).
        $owner = User::findOrFail($account->user_id);
        $headers = ['Authorization' => 'Bearer '.$owner->createToken('iphone', ['device'])->plainTextToken, 'Accept' => 'application/json'];
        $this->postJson('/api/v1/mail/attachments/'.$att->id.'/save', ['target' => 'gallery'], $headers)
            ->assertStatus(422);
    }

    public function test_sweep_reclaims_orphan_attachment_blob(): void
    {
        $user = User::factory()->create();
        // An attachment ledger row with no MailAttachment referencing it, past grace.
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('mail/att/'.$blob, 'orphan-bytes');
        (new MailBlob)->forceFill([
            'blob' => $blob,
            'user_id' => $user->id,
            'kind' => 'attachment',
            'size' => 12,
            'created_at' => now()->subDays(2),
        ])->save();

        $this->artisan(SweepOrphanMailBlobs::class)->assertSuccessful();

        Storage::disk(config('files.disk'))->assertMissing('mail/att/'.$blob);
        $this->assertDatabaseMissing('mail_blobs', ['blob' => $blob]);
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
