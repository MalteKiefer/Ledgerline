<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SweepOrphanMailBlobs;
use App\Models\FileEntry;
use App\Models\FinanceReceipt;
use App\Models\MailAccount;
use App\Models\MailAttachment;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\MailRule;
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
            '<html><head><style>@font-face{font-family:Tracker;src:url(https://fonts.example/font.woff2)} p{color:#123456;background:url(https://tracker.example/bg.png)}'
                // Escaped and comment-split spellings of url(), plus an @import
                // with no terminating semicolon: all read as a fetch in a
                // browser, none of them match a naive keyword filter.
                .'@import "https://esc.example/late.css"'
                .'div{background:'.chr(92).'75 rl(https://esc.example/escaped.png)}'
                .'span{background:url/*x*/(https://esc.example/comment.png)}'
                .'</style></head><body><p style="font-weight:bold;background-image:url(https://tracker.example/pixel.png)">Hi</p>'
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

    /** A message carrying one PDF attachment — an invoice, as they actually arrive. */
    private function invoiceEml(string $messageId = 'inv1@example.com'): string
    {
        $boundary = 'INVBOUND';
        $pdf = base64_encode("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return implode("\r\n", [
            'From: Rechnung <billing@netcup.de>',
            'To: bob@example.com',
            'Subject: Ihre Rechnung',
            'Message-ID: <'.$messageId.'>',
            'Date: Wed, 05 Aug 2026 10:00:00 +0000',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
            '',
            '--'.$boundary,
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Rechnung im Anhang.',
            '',
            '--'.$boundary,
            'Content-Type: application/pdf; name="rechnung.pdf"',
            'Content-Disposition: attachment; filename="rechnung.pdf"',
            'Content-Transfer-Encoding: base64',
            '',
            $pdf,
            '',
            '--'.$boundary.'--',
            '',
        ]);
    }

    private function ingestInvoice(MailAccount $account, string $name = '1700000001.M1,U=8:2,S', string $messageId = 'inv1@example.com'): void
    {
        $path = $this->maildir.'/cur/'.$name;
        file_put_contents($path, $this->invoiceEml($messageId));
        app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);
    }

    public function test_an_invoice_attachment_can_be_filed_as_a_receipt(): void
    {
        $account = $this->account();
        $this->ingestInvoice($account);
        $att = MailAttachment::query()->where('user_id', $account->user_id)->where('inline', false)->firstOrFail();
        $user = User::findOrFail($account->user_id);

        $res = $this->actingAs($user)
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'finance'])
            ->assertOk()
            ->assertJson(['ok' => true, 'target' => 'finance', 'duplicate' => false]);

        $receipt = FinanceReceipt::query()->withoutGlobalScopes()->where('user_id', $account->user_id)->firstOrFail();
        $this->assertSame('rechnung.pdf', $receipt->name);
        $this->assertSame('application/pdf', $receipt->mime);
        $this->assertNotNull($receipt->sig, 'a signature, or the inbox cannot dedup it');
        Storage::disk(config('files.disk'))->assertExists((string) $receipt->blob_path);

        // Filing it twice is not two receipts.
        $this->actingAs($user)
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'finance'])
            ->assertOk()
            ->assertJson(['duplicate' => true, 'receipt_id' => (int) $res->json('receipt_id')]);
        $this->assertSame(1, FinanceReceipt::query()->withoutGlobalScopes()->count());
    }

    public function test_a_text_attachment_is_not_a_receipt(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);
        $att = MailAttachment::query()->where('message_id', $msg->id)->where('inline', false)->firstOrFail();

        // notes.txt is a document, but not one a receipt can be made of.
        $this->actingAs(User::findOrFail($account->user_id))
            ->postJson(route('mail.attachments.save', $att->id), ['target' => 'finance'])
            ->assertUnprocessable()
            ->assertJson(['ok' => false, 'detail' => 'unsupported_type']);
        $this->assertSame(0, FinanceReceipt::query()->withoutGlobalScopes()->count());
    }

    public function test_a_rule_files_invoices_on_arrival(): void
    {
        $account = $this->account();
        // user_id is owner-stamped from auth, so it is not mass-assignable.
        (new MailRule)->forceFill([
            'user_id' => $account->user_id, 'name' => 'Rechnungen', 'enabled' => true, 'priority' => 0,
            'match_json' => ['from' => 'billing@netcup.de'],
            'action_json' => ['file_receipt' => true],
        ])->save();

        $this->ingestInvoice($account);

        $receipt = FinanceReceipt::query()->withoutGlobalScopes()->where('user_id', $account->user_id)->firstOrFail();
        $this->assertSame('rechnung.pdf', $receipt->name);

        // A resend must not become a second receipt (content dedup).
        $this->ingestInvoice($account, '1700000002.M1,U=9:2,S', 'inv2@example.com');
        $this->assertSame(1, FinanceReceipt::query()->withoutGlobalScopes()->count());
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
        $this->assertStringStartsWith('inline;', (string) $res->headers->get('Content-Disposition'));
        $this->assertSame('quarterly numbers here', trim($res->streamedContent()));

        // Browser-renderable PDFs still become real downloads when explicitly requested.
        $att->forceFill(['filename' => 'report.pdf', 'content_type' => 'application/pdf'])->save();
        $download = $this->actingAs(User::findOrFail($account->user_id))
            ->get(route('mail.attachments.raw', [$att->id, 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('report.pdf', (string) $download->headers->get('Content-Disposition'));

        // Foreign user → 404.
        $this->actingAs(User::factory()->create())
            ->get(route('mail.attachments.raw', $att->id))->assertNotFound();
    }

    public function test_body_endpoint_blocks_remote_images_by_default_and_allows_one_explicit_view(): void
    {
        $account = $this->account();
        $msg = $this->ingest($account);
        $owner = User::findOrFail($account->user_id);

        // Remote img is stripped and cid is rewritten to data:.
        $res = $this->actingAs($owner)->get(route('mail.messages.body', $msg->id))->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('img-src data:', (string) $csp);
        $this->assertStringNotContainsString('https:', (string) $csp);
        $body = $res->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('data:image/png;base64,', $body);
        $this->assertStringNotContainsString('tracker.example', $body);
        $this->assertStringNotContainsString('fonts.example', $body);
        $this->assertStringNotContainsString('@font-face', $body);
        // Escaped, comment-split and semicolon-less spellings must not survive
        // either — a browser resolves all three back into a resource fetch.
        $this->assertStringNotContainsString('esc.example', $body);
        $this->assertStringNotContainsString('@import', $body);
        $this->assertStringContainsString('font-weight:bold', str_replace(' ', '', $body));

        // The reader can load remote images for this one explicit view only.
        $res = $this->actingAs($owner)->get(route('mail.messages.body', [$msg->id, 'remote' => 1]))->assertOk();
        $this->assertStringContainsString('https:', (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('tracker.example/px.gif', (string) $res->getContent());

        // A stale preference alone cannot enable tracking on a later request.
        UserSetting::for($owner->id)->forceFill(['mail_load_remote' => true])->save();
        $res = $this->actingAs($owner)->get(route('mail.messages.body', $msg->id))->assertOk();
        $this->assertStringNotContainsString('https:', (string) $res->headers->get('Content-Security-Policy'));
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

    public function test_the_attachment_index_lists_real_attachments_newest_first(): void
    {
        // "The mail with the PDF" without remembering which mail it was.
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $message = $this->archive($user, $account, 'Invoice mail');
        $this->attach($user, $message, 'invoice.pdf', 'application/pdf', false);
        $this->attach($user, $message, 'photo.jpg', 'image/jpeg', false);
        // An inline image is part of how the message looks, not a document the
        // sender attached — listing signatures would bury the real files.
        $this->attach($user, $message, 'logo.gif', 'image/gif', true);

        $rows = $this->getJson(route('mail.attachments.index'))->assertOk()->json('data');
        $names = collect($rows)->pluck('filename')->all();

        $this->assertNotContains('logo.gif', $names);
        $this->assertCount(2, $names);
        $this->assertSame('Invoice mail', $rows[0]['subject'], 'the row carries the message it belongs to');

        // Filters
        $this->assertSame(['invoice.pdf'], collect($this->getJson(route('mail.attachments.index', ['type' => 'pdf']))->json('data'))->pluck('filename')->all());
        $this->assertSame(['photo.jpg'], collect($this->getJson(route('mail.attachments.index', ['type' => 'image']))->json('data'))->pluck('filename')->all());
        $this->assertSame(['invoice.pdf'], collect($this->getJson(route('mail.attachments.index', ['q' => 'INVOI']))->json('data'))->pluck('filename')->all());
    }

    public function test_the_attachment_index_never_shows_another_account_or_trashed_mail(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner);
        $live = $this->archive($owner, $account, 'Live');
        $this->attach($owner, $live, 'keep.pdf', 'application/pdf', false);
        $binned = $this->archive($owner, $account, 'Binned', trashed: true);
        $this->attach($owner, $binned, 'gone.pdf', 'application/pdf', false);

        $this->assertSame(['keep.pdf'], collect($this->getJson(route('mail.attachments.index'))->json('data'))->pluck('filename')->all());

        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create());
        $this->assertCount(0, $this->getJson(route('mail.attachments.index'))->assertOk()->json('data'));
    }

    private function archive(User $user, MailAccount $account, string $subject, bool $trashed = false): MailMessage
    {
        $m = new MailMessage;
        $m->forceFill([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id,
            'folder' => 'INBOX', 'subject' => $subject, 'from_email' => 'a@b.c', 'from_name' => 'A',
            'to_json' => [], 'seen' => false, 'size' => 10, 'content_hash' => (string) Str::uuid(),
            'has_attachment' => true, 'date' => now(), 'created_at' => now(),
            'trashed_at' => $trashed ? now() : null,
        ])->save();

        return $m;
    }

    private function attach(User $user, MailMessage $message, string $name, string $type, bool $inline): void
    {
        (new MailAttachment)->forceFill([
            'id' => (string) Str::uuid(), 'message_id' => $message->id, 'user_id' => $user->id,
            'blob' => (string) Str::uuid(), 'filename' => $name, 'content_type' => $type,
            'inline' => $inline, 'size' => 100, 'created_at' => now(),
        ])->save();
    }
}
