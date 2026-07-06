<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Services\Mail\ImapReader;
use App\Services\Mail\SmtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MailAccountTestConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_test(): void
    {
        $this->post(route('mail.accounts.test'), [])->assertRedirect(route('login'));
    }

    public function test_account_pages_render_and_are_owner_scoped(): void
    {
        $user = $this->signIn();
        $this->get(route('mail.accounts.create'))->assertOk()->assertSee('mailAccountEdit', false);

        $account = MailAccount::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret',
        ]);
        $this->get(route('mail.accounts.edit-page', $account))->assertOk()->assertSee('imap.example.com', false);

        // Another user cannot open the edit page.
        $this->signIn();
        $this->get(route('mail.accounts.edit-page', $account))->assertNotFound();
    }

    public function test_a_successful_test_reports_both_imap_and_smtp_ok(): void
    {
        $this->signIn();

        $reader = Mockery::mock(ImapReader::class);
        $reader->shouldReceive('listFolders')->once()->andReturn([]);
        $this->app->instance(ImapReader::class, $reader);

        $smtp = Mockery::mock(SmtpSender::class);
        $smtp->shouldReceive('verify')->once();
        $this->app->instance(SmtpSender::class, $smtp);

        $this->postJson(route('mail.accounts.test'), [
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'me@example.com', 'password' => 'secret',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        ])
            ->assertOk()
            ->assertJsonPath('imap.ok', true)
            ->assertJsonPath('smtp.ok', true)
            ->assertJsonPath('smtp.configured', true);
    }

    public function test_failures_are_reported_without_a_500(): void
    {
        $this->signIn();

        $reader = Mockery::mock(ImapReader::class);
        $reader->shouldReceive('listFolders')->andThrow(new RuntimeException('Authentication failed'));
        $this->app->instance(ImapReader::class, $reader);

        $smtp = Mockery::mock(SmtpSender::class);
        $smtp->shouldReceive('verify')->andThrow(new RuntimeException('SMTP connection failed: refused'));
        $this->app->instance(SmtpSender::class, $smtp);

        $this->postJson(route('mail.accounts.test'), [
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'me@example.com', 'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonPath('imap.ok', false)
            ->assertJsonPath('imap.error', 'Authentication failed');
    }

    public function test_blank_password_reuses_the_stored_one_and_is_owner_scoped(): void
    {
        $user = $this->signIn();
        $account = MailAccount::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'stored-secret',
        ]);

        $captured = null;
        $reader = Mockery::mock(ImapReader::class);
        $reader->shouldReceive('listFolders')->once()->andReturnUsing(function ($creds) use (&$captured) {
            $captured = $creds->password;

            return [];
        });
        $this->app->instance(ImapReader::class, $reader);

        $this->postJson(route('mail.accounts.test'), [
            'account_id' => $account->id,
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'me@example.com', 'password' => '', // blank → reuse stored
        ])->assertOk();

        $this->assertSame('stored-secret', $captured);
    }
}
