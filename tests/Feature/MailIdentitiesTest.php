<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailIdentity;
use App\Models\User;
use App\Services\Mail\MailSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailIdentitiesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(User $owner): MailAccount
    {
        return MailAccount::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls',
            'smtp_username' => 'me@example.com', 'smtp_password' => 'smtp-secret',
            'from_name' => 'Me', 'reply_to' => null, 'signature' => 'Cheers',
        ]);
    }

    public function test_creating_an_account_seeds_a_default_identity(): void
    {
        $this->signIn();

        $this->postJson(route('mail.accounts.store'), [
            'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret',
            'from_name' => 'Me', 'signature' => 'Cheers',
        ])->assertCreated()
            ->assertJsonPath('identities.0.fromEmail', 'me@example.com')
            ->assertJsonPath('identities.0.isDefault', true);

        $this->assertSame(1, MailIdentity::count());
    }

    public function test_owner_can_crud_identities(): void
    {
        $user = $this->signIn();
        $account = $this->makeAccount($user);
        $account->identities()->create(['from_email' => 'me@example.com', 'is_default' => true]);

        // Create
        $this->postJson(route('mail.identities.store', $account), [
            'from_name' => 'Sales', 'from_email' => 'sales@example.com', 'signature' => 'BR',
        ])->assertCreated()->assertJsonPath('fromEmail', 'sales@example.com');

        // Index
        $this->getJson(route('mail.identities.index', $account))
            ->assertOk()->assertJsonCount(2, 'identities');

        $identity = MailIdentity::where('from_email', 'sales@example.com')->first();

        // Update
        $this->putJson(route('mail.identities.update', [$account, $identity]), [
            'from_email' => 'sales@example.com', 'from_name' => 'Sales Team',
        ])->assertOk()->assertJsonPath('fromName', 'Sales Team');

        // Delete
        $this->deleteJson(route('mail.identities.destroy', [$account, $identity]))
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(1, $account->identities()->count());
    }

    public function test_other_user_cannot_touch_identities(): void
    {
        $owner = User::factory()->create();
        $account = $this->makeAccount($owner);
        $identity = $account->identities()->create(['from_email' => 'me@example.com', 'is_default' => true]);

        $this->signIn(); // a different user

        $this->getJson(route('mail.identities.index', $account))->assertForbidden();
        $this->postJson(route('mail.identities.store', $account), ['from_email' => 'x@example.com'])->assertForbidden();
        $this->putJson(route('mail.identities.update', [$account, $identity]), ['from_email' => 'x@example.com'])->assertForbidden();
        $this->deleteJson(route('mail.identities.destroy', [$account, $identity]))->assertForbidden();
    }

    public function test_the_last_identity_cannot_be_deleted(): void
    {
        $user = $this->signIn();
        $account = $this->makeAccount($user);
        $identity = $account->identities()->create(['from_email' => 'me@example.com', 'is_default' => true]);

        $this->deleteJson(route('mail.identities.destroy', [$account, $identity]))
            ->assertStatus(422);

        $this->assertSame(1, $account->identities()->count());
    }

    public function test_setting_one_default_unsets_the_others(): void
    {
        $user = $this->signIn();
        $account = $this->makeAccount($user);
        $first = $account->identities()->create(['from_email' => 'a@example.com', 'is_default' => true]);
        $second = $account->identities()->create(['from_email' => 'b@example.com', 'is_default' => false]);

        $this->putJson(route('mail.identities.update', [$account, $second]), [
            'from_email' => 'b@example.com', 'is_default' => true,
        ])->assertOk()->assertJsonPath('isDefault', true);

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
    }

    public function test_deleting_the_default_promotes_another(): void
    {
        $user = $this->signIn();
        $account = $this->makeAccount($user);
        $default = $account->identities()->create(['from_email' => 'a@example.com', 'is_default' => true]);
        $other = $account->identities()->create(['from_email' => 'b@example.com', 'is_default' => false]);

        $this->deleteJson(route('mail.identities.destroy', [$account, $default]))->assertOk();

        $this->assertTrue($other->refresh()->is_default);
    }

    public function test_sending_with_an_identity_uses_its_from(): void
    {
        $user = $this->signIn();
        $account = $this->makeAccount($user);
        $account->identities()->create(['from_email' => 'me@example.com', 'is_default' => true]);
        $identity = $account->identities()->create([
            'from_name' => 'Sales', 'from_email' => 'sales@example.com', 'reply_to' => 'noreply@example.com', 'is_default' => false,
        ]);

        // SmtpSender is final (unmockable) and build() is pure (no network), so
        // exercise the draft endpoint: it builds the real MIME and hands the raw
        // string to MailSource::appendMessage, which we capture and assert on.
        $raw = null;
        $this->mock(MailSource::class, function ($mock) use (&$raw): void {
            $mock->shouldReceive('appendMessage')->andReturnUsing(function ($creds, $folder, $body) use (&$raw): void {
                $raw = $body;
            });
        });

        $this->postJson(route('mail.draft'), [
            'account_id' => $account->id,
            'identity_id' => $identity->id,
            'to' => ['someone@example.com'],
            'subject' => 'Hi', 'body' => 'Body',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertNotNull($raw);
        // From carries the identity's name + address, Reply-To its reply address.
        $this->assertStringContainsString('sales@example.com', (string) $raw);
        $this->assertStringContainsString('Sales', (string) $raw);
        $this->assertStringContainsString('noreply@example.com', (string) $raw);
    }

    public function test_sending_with_a_foreign_identity_is_rejected(): void
    {
        $user = $this->signIn();
        $account = $this->makeAccount($user);
        $account->identities()->create(['from_email' => 'me@example.com', 'is_default' => true]);

        $otherOwner = User::factory()->create();
        $otherAccount = $this->makeAccount($otherOwner);
        $foreign = $otherAccount->identities()->create(['from_email' => 'x@example.com', 'is_default' => true]);

        $this->postJson(route('mail.send'), [
            'account_id' => $account->id,
            'identity_id' => $foreign->id,
            'to' => ['someone@example.com'],
            'subject' => 'Hi', 'body' => 'Body',
        ])->assertForbidden();
    }
}
