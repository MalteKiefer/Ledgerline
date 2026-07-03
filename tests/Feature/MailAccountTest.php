<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('mail.accounts'))->assertRedirect(route('login'));
    }

    public function test_it_creates_an_account_with_an_encrypted_password(): void
    {
        $this->signIn();

        $this->postJson(route('mail.accounts.store'), [
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret-pass', 'validate_cert' => true,
        ])->assertCreated()->assertJsonMissing(['password' => 'secret-pass']);

        $account = MailAccount::first();
        $this->assertSame('secret-pass', $account->password);
        $raw = DB::table('mail_accounts')->value('password');
        $this->assertStringNotContainsString('secret-pass', (string) $raw);
    }

    public function test_index_never_returns_the_password(): void
    {
        $this->signIn();
        MailAccount::create(['name' => 'A', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p']);

        $this->getJson(route('mail.accounts'))->assertOk()->assertDontSee('"password"', false);
    }

    public function test_a_blank_password_keeps_the_stored_one(): void
    {
        $this->signIn();
        $a = MailAccount::create(['name' => 'A', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'keep-me']);

        $this->putJson(route('mail.accounts.update', $a), [
            'name' => 'A2', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => '',
        ])->assertOk();

        $this->assertSame('keep-me', $a->refresh()->password);
        $this->assertSame('A2', $a->name);
    }

    public function test_stats_requires_a_known_account(): void
    {
        $this->signIn();
        $this->post(route('mail.stats'), ['account_id' => 999999])->assertInvalid('account_id');
    }
}
