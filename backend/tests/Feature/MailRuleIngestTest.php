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
use Tests\TestCase;

/**
 * Rules act at ingest time, before anything is visible: skip means the message
 * is never archived at all. A regression here drops mail silently, which is the
 * one failure the archive must not have — so the actions are asserted against a
 * real ingest, not against the evaluator in isolation.
 */
class MailRuleIngestTest extends TestCase
{
    use RefreshDatabase;

    private string $maildir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        $this->maildir = sys_get_temp_dir().'/mailrule-'.bin2hex(random_bytes(6));
        @mkdir($this->maildir.'/cur', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->maildir.'/cur/*') as $f) {
            @unlink((string) $f);
        }
        @rmdir($this->maildir.'/cur');
        @rmdir($this->maildir);
        parent::tearDown();
    }

    private function eml(string $from = 'newsletter@spam.example', string $subject = 'Weekly digest'): string
    {
        return implode("\r\n", [
            'From: Sender <'.$from.'>',
            'To: bob@example.com',
            'Subject: '.$subject,
            'Message-ID: <'.bin2hex(random_bytes(6)).'@example.com>',
            'Date: Wed, 05 Aug 2026 10:00:00 +0000',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'body',
            '',
        ]);
    }

    private function ingest(MailAccount $account, string $raw): IngestStatus
    {
        $path = $this->maildir.'/cur/'.bin2hex(random_bytes(6)).':2,S';
        file_put_contents($path, $raw);

        return app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path)->status;
    }

    public function test_a_skip_rule_keeps_the_message_out_of_the_archive(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        MailRule::forceCreate([
            'user_id' => $user->id, 'name' => 'Drop newsletters', 'enabled' => true,
            'match_json' => ['from' => 'spam.example'], 'action_json' => ['skip' => true],
        ]);

        $this->assertSame(IngestStatus::SkippedRule, $this->ingest($account, $this->eml()));
        $this->assertSame(0, MailMessage::withoutGlobalScopes()->count());

        // A message the rule does not match is still archived.
        $this->ingest($account, $this->eml('friend@example.com'));
        $this->assertSame(1, MailMessage::withoutGlobalScopes()->count());
    }

    public function test_mark_read_trash_and_label_apply_at_ingest(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $label = MailLabel::forceCreate(['user_id' => $user->id, 'name' => 'Bulk', 'color' => '#888888']);
        MailRule::forceCreate([
            'user_id' => $user->id, 'name' => 'Quiet bulk', 'enabled' => true,
            'match_json' => ['subject' => 'digest'],
            'action_json' => ['mark_read' => true, 'trash' => true, 'add_label' => $label->id],
        ]);

        $this->ingest($account, $this->eml());

        $message = MailMessage::withoutGlobalScopes()->firstOrFail();
        $this->assertTrue((bool) $message->seen);
        $this->assertNotNull($message->trashed_at);
        $this->assertTrue($message->labels()->withoutGlobalScopes()->whereKey($label->id)->exists());
    }

    public function test_a_rule_without_conditions_never_matches(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        // A catch-all would silently swallow every message ever ingested.
        MailRule::forceCreate([
            'user_id' => $user->id, 'name' => 'Empty', 'enabled' => true,
            'match_json' => [], 'action_json' => ['skip' => true],
        ]);

        $this->ingest($account, $this->eml());
        $this->assertSame(1, MailMessage::withoutGlobalScopes()->count());
    }

    public function test_another_users_rule_does_not_act_on_this_account(): void
    {
        $stranger = User::factory()->create();
        MailRule::forceCreate([
            'user_id' => $stranger->id, 'name' => 'Drop all', 'enabled' => true,
            'match_json' => ['from' => 'spam.example'], 'action_json' => ['skip' => true],
        ]);

        $account = MailAccount::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->ingest($account, $this->eml());

        $this->assertSame(1, MailMessage::withoutGlobalScopes()->count());
    }
}
