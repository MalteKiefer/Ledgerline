<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailLog;
use App\Models\User;
use App\Support\Mail\MailLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_records_owner_scoped_redacted_lines(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        MailLogger::record($account, 'info', 'folder_fetched', 'Sent', 'Bearer sk-secret token=abc 12 new message(s)');

        $log = MailLog::query()->firstOrFail();
        $this->assertSame($account->id, $log->account_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Sent', $log->folder);
        $this->assertStringNotContainsString('sk-secret', (string) $log->message);
        $this->assertStringNotContainsString('abc', (string) $log->message);
    }

    public function test_owner_can_read_their_account_logs(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        MailLogger::record($account, 'warn', 'chunk_ingested', 'INBOX', 'archived 5, failed 1');

        $this->actingAs($user)
            ->getJson("/api/v1/mail/accounts/{$account->id}/logs")
            ->assertOk()
            ->assertJsonPath('data.0.event', 'chunk_ingested')
            ->assertJsonPath('data.0.level', 'warn')
            ->assertJsonPath('data.0.folder', 'INBOX');
    }

    public function test_a_different_user_cannot_read_the_logs(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        MailLogger::record($account, 'info', 'sync_started');

        $this->actingAs($other)
            ->getJson("/api/v1/mail/accounts/{$account->id}/logs")
            ->assertNotFound();
    }

    public function test_prune_removes_old_lines_only(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        MailLogger::record($account, 'info', 'recent');
        MailLog::query()->create([
            'account_id' => $account->id, 'user_id' => $user->id,
            'level' => 'info', 'event' => 'old', 'created_at' => now()->subDays(90),
        ]);

        $this->artisan('mail-logs:prune')->assertSuccessful();

        $this->assertSame(1, MailLog::query()->count());
        $this->assertSame('recent', MailLog::query()->firstOrFail()->event);
    }
}
