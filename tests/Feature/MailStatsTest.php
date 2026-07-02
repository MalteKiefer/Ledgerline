<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailStatsTest extends TestCase
{
    use RefreshDatabase;

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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'me@example.com',
            'password' => 'secret',
            'validate_cert' => true,
        ], $overrides);
    }

    public function test_guests_cannot_fetch_stats(): void
    {
        $this->post(route('mail.stats'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_valid_request_returns_stats(): void
    {
        $this->signIn();
        $this->fakeStats([
            'total' => 42, 'unseen' => 3, 'quotaUsed' => 1024, 'quotaLimit' => 4096,
            'folders' => [['name' => 'INBOX', 'total' => 40, 'unseen' => 3]],
        ]);

        $this->postJson(route('mail.stats'), $this->payload())
            ->assertOk()
            ->assertJson(['total' => 42, 'unseen' => 3, 'quotaUsed' => 1024, 'quotaLimit' => 4096]);
    }

    public function test_plaintext_encryption_is_rejected(): void
    {
        $this->signIn();

        // Web-route validation failures redirect with session errors (JSON error
        // rendering is limited to api/* in bootstrap/app.php).
        $this->from(route('mail.index'))->post(route('mail.stats'), $this->payload(['encryption' => 'none']))
            ->assertRedirect()->assertSessionHasErrors('encryption');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->signIn();

        $this->from(route('mail.index'))->post(route('mail.stats'), ['encryption' => 'ssl'])
            ->assertRedirect()->assertSessionHasErrors(['host', 'port', 'username', 'password']);
    }

    public function test_an_invalid_port_is_rejected(): void
    {
        $this->signIn();

        $this->from(route('mail.index'))->post(route('mail.stats'), $this->payload(['port' => 70000]))
            ->assertRedirect()->assertSessionHasErrors('port');
    }

    public function test_a_connection_failure_returns_410_style_generic_error(): void
    {
        $this->signIn();
        $this->failingStats();

        $this->postJson(route('mail.stats'), $this->payload())
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
