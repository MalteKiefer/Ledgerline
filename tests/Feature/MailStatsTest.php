<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailStatsTest extends TestCase
{
    use RefreshDatabase;

    private function account(): MailAccount
    {
        return MailAccount::create([
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret',
        ]);
    }

    private function fakeStats(array $stats): void
    {
        $this->app->instance(ImapStats::class, new class($stats) implements ImapStats
        {
            public function __construct(private array $stats) {}

            public function fetch(ImapCredentials $credentials): array
            {
                return $this->stats;
            }
        });
    }

    private function failingStats(): void
    {
        $this->app->instance(ImapStats::class, new class implements ImapStats
        {
            public function fetch(ImapCredentials $credentials): array
            {
                throw new \RuntimeException('connect failed');
            }
        });
    }

    public function test_guests_cannot_fetch_stats(): void
    {
        $this->post(route('mail.stats'), ['account_id' => 1])->assertRedirect(route('login'));
    }

    public function test_valid_request_returns_stats(): void
    {
        $this->signIn();
        $this->fakeStats([
            'total' => 42, 'unseen' => 3, 'quotaUsed' => 1024, 'quotaLimit' => 4096,
            'folders' => [['name' => 'INBOX', 'total' => 40, 'unseen' => 3]],
        ]);

        $this->postJson(route('mail.stats'), ['account_id' => $this->account()->id])
            ->assertOk()
            ->assertJson(['total' => 42, 'unseen' => 3, 'quotaUsed' => 1024, 'quotaLimit' => 4096]);
    }

    public function test_an_unknown_account_is_rejected(): void
    {
        $this->signIn();

        $this->from(route('mail.index'))->post(route('mail.stats'), ['account_id' => 999999])
            ->assertRedirect()->assertSessionHasErrors('account_id');
    }

    public function test_a_connection_failure_returns_a_generic_error(): void
    {
        $this->signIn();
        $this->failingStats();

        $this->postJson(route('mail.stats'), ['account_id' => $this->account()->id])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
