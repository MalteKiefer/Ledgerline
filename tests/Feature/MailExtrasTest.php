<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailLabel;
use App\Models\MailMessage;
use App\Models\MailRule;
use App\Models\User;
use App\Services\Mail\IngestStatus;
use App\Services\Mail\MaildirIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailExtrasTest extends TestCase
{
    use RefreshDatabase;

    private string $maildir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->maildir = sys_get_temp_dir().'/mailextra-'.bin2hex(random_bytes(6));
        @mkdir($this->maildir.'/cur', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->maildir);
        parent::tearDown();
    }

    private function account(User $user, array $o = []): MailAccount
    {
        return MailAccount::factory()->create(array_merge(['user_id' => $user->id], $o));
    }

    private function ingest(MailAccount $account, string $raw): IngestStatus
    {
        $path = $this->maildir.'/cur/'.bin2hex(random_bytes(4)).':2,';
        file_put_contents($path, $raw);

        return app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path)->status;
    }

    private function eml(array $headers, string $body = 'body'): string
    {
        return implode("\r\n", [...$headers, 'Content-Type: text/plain', '', $body, '']);
    }

    private function mkMsg(User $user, MailAccount $account, array $attrs = []): MailMessage
    {
        $id = (string) Str::uuid();
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => $id, 'user_id' => $user->id, 'account_id' => $account->id, 'folder' => 'INBOX',
            'content_hash' => hash('sha256', $id), 'size' => 12, 'subject' => 'S', 'created_at' => now(),
        ], $attrs))->save();
        Storage::disk(config('files.disk'))->put('mail/'.$id, "Subject: S\r\n\r\n".$id);

        return $m;
    }

    public function test_threading_groups_a_reply_with_its_parent(): void
    {
        $account = $this->account(User::factory()->create());
        $this->ingest($account, $this->eml(['From: a@x.com', 'Subject: Hi', 'Message-ID: <root@x.com>']));
        $this->ingest($account, $this->eml(['From: b@x.com', 'Subject: Re: Hi', 'Message-ID: <reply@x.com>', 'In-Reply-To: <root@x.com>'], 'reply'));

        $threads = MailMessage::query()->where('user_id', $account->user_id)->pluck('thread_id')->unique();
        $this->assertCount(1, $threads);
        $this->assertNotNull($threads->first());
    }

    public function test_ingest_rule_skip_does_not_archive(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        (new MailRule)->forceFill([
            'user_id' => $user->id, 'name' => 'drop', 'enabled' => true, 'priority' => 0,
            'match_json' => ['from' => 'spam@bad.com'], 'action_json' => ['skip' => true],
        ])->save();

        $status = $this->ingest($account, $this->eml(['From: spam@bad.com', 'Subject: junk', 'Message-ID: <j@x>']));

        $this->assertSame(IngestStatus::SkippedRule, $status);
        $this->assertSame(0, MailMessage::query()->where('user_id', $user->id)->count());
    }

    public function test_ingest_rule_marks_read_and_labels(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $label = new MailLabel;
        $label->forceFill(['user_id' => $user->id, 'name' => 'Auto', 'color' => '#123456'])->save();
        (new MailRule)->forceFill([
            'user_id' => $user->id, 'name' => 'auto', 'enabled' => true, 'priority' => 0,
            'match_json' => ['subject' => 'receipt'], 'action_json' => ['mark_read' => true, 'add_label' => $label->id],
        ])->save();

        $this->ingest($account, $this->eml(['From: shop@x.com', 'Subject: Your receipt', 'Message-ID: <r@x>']));

        $m = MailMessage::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($m->seen);
        $this->assertTrue($m->labels()->where('mail_labels.id', $label->id)->exists());
    }

    public function test_label_crud_bulk_apply_and_filter(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->actingAs($user);

        $label = $this->postJson(route('mail.labels.store'), ['name' => 'Work', 'color' => '#abcdef'])
            ->assertCreated()->json('label');
        $a = $this->mkMsg($user, $account);
        $b = $this->mkMsg($user, $account);

        $this->postJson(route('mail.messages.labels'), ['ids' => [$a->id, $b->id], 'add' => [$label['id']]])
            ->assertOk()->assertJson(['updated' => 2]);

        // Filter by label.
        $this->getJson(route('mail.messages.index', ['label' => $label['id']]))
            ->assertOk()->assertJsonCount(2, 'data');

        // Remove from one.
        $this->postJson(route('mail.messages.labels'), ['ids' => [$a->id], 'remove' => [$label['id']]])->assertOk();
        $this->getJson(route('mail.messages.index', ['label' => $label['id']]))->assertJsonCount(1, 'data');

        // A foreign user cannot see or delete it.
        $this->actingAs(User::factory()->create())
            ->deleteJson(route('mail.labels.destroy', $label['id']))->assertNotFound();
    }

    public function test_saved_search_crud_owner_scoped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson(route('mail.saved-searches.store'), ['name' => 'Unread', 'filters' => ['seen' => false]])
            ->assertCreated()->json('saved_search.id');
        $this->getJson(route('mail.saved-searches.index'))->assertOk()->assertJsonCount(1, 'saved_searches');

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('mail.saved-searches.destroy', $id))->assertNotFound();
    }

    public function test_export_mbox_and_zip(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $a = $this->mkMsg($user, $account);
        $b = $this->mkMsg($user, $account);
        $this->actingAs($user);

        $mbox = $this->post(route('mail.export'), ['format' => 'mbox', 'ids' => [$a->id, $b->id]])->assertOk();
        $this->assertSame('application/mbox', $mbox->headers->get('Content-Type'));
        $body = $mbox->streamedContent();
        $this->assertStringContainsString('From MAILER-DAEMON', $body);
        $this->assertStringContainsString($a->id, $body);
        $this->assertStringContainsString($b->id, $body);

        $zip = $this->post(route('mail.export'), ['format' => 'zip', 'folder' => 'INBOX'])->assertOk();
        $this->assertStringContainsString('zip', (string) $zip->headers->get('Content-Type'));

        // Empty selection → 422.
        $this->postJson(route('mail.export'), ['format' => 'mbox'])->assertStatus(422);
    }

    public function test_stats_reports_totals_per_account_and_folder(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->mkMsg($user, $account, ['folder' => 'INBOX', 'size' => 100]);
        $this->mkMsg($user, $account, ['folder' => 'Sent', 'size' => 50]);

        $res = $this->actingAs($user)->getJson(route('mail.stats'))->assertOk();
        $this->assertSame(2, $res->json('total_messages'));
        $this->assertSame(150, $res->json('total_bytes'));
        $this->assertCount(1, $res->json('per_account'));
        $this->assertCount(2, $res->json('per_folder'));
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
