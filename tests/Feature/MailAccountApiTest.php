<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use App\Models\User;
use App\Support\Mail\ImapProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Personal',
            'host' => 'imap.example.com',
            'port' => 993,
            'username' => 'me@example.com',
            'password' => 'secret-imap-pw',
            'encryption' => 'ssl',
        ], $overrides);
    }

    public function test_web_crud_and_password_never_serialised(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $created = $this->postJson(route('mail.accounts.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('account.name', 'Personal')
            ->assertJsonMissingPath('account.password')
            ->json('account.id');

        // The stored password is encrypted at rest and never leaves via the API.
        $index = $this->getJson(route('mail.accounts.index'))->assertOk();
        $this->assertArrayNotHasKey('password', $index->json('accounts.0'));
        $account = MailAccount::findOrFail($created);
        $this->assertSame('secret-imap-pw', $account->password); // decrypts via cast
        $this->assertNotSame('secret-imap-pw', $account->getRawOriginal('password'));

        // Update with a blank password keeps the stored one (KeepBlankSecrets).
        $this->putJson(route('mail.accounts.update', $created), $this->payload(['name' => 'Renamed', 'password' => '']))
            ->assertOk()->assertJsonPath('account.name', 'Renamed');
        $this->assertSame('secret-imap-pw', MailAccount::findOrFail($created)->password);

        $this->deleteJson(route('mail.accounts.destroy', $created))->assertNoContent();
        $this->assertDatabaseMissing('mail_accounts', ['id' => $created]);
    }

    public function test_api_twin_creates_owner_scoped_account(): void
    {
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('iphone', ['device'])->plainTextToken, 'Accept' => 'application/json'];

        $this->postJson('/api/v1/mail/accounts', $this->payload(), $headers)
            ->assertCreated()
            ->assertJsonMissingPath('account.password');

        $this->getJson('/api/v1/mail/accounts', $headers)->assertOk()->assertJsonCount(1, 'accounts');
    }

    public function test_foreign_account_is_404_not_403(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $this->actingAs(User::factory()->create());
        $this->putJson(route('mail.accounts.update', $account->id), $this->payload())->assertNotFound();
        $this->deleteJson(route('mail.accounts.destroy', $account->id))->assertNotFound();
        $this->getJson(route('mail.accounts.status', $account->id))->assertNotFound();
    }

    public function test_validation_rejects_bad_input(): void
    {
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('iphone', ['device'])->plainTextToken, 'Accept' => 'application/json'];

        $this->postJson('/api/v1/mail/accounts', $this->payload(['encryption' => 'plaintext']), $headers)
            ->assertStatus(422);
        $this->postJson('/api/v1/mail/accounts', $this->payload(['port' => 0]), $headers)
            ->assertStatus(422);
        $this->postJson('/api/v1/mail/accounts', $this->payload(['host' => '169.254.169.254']), $headers)
            ->assertStatus(422); // SafeHost blocks link-local / cloud-metadata
    }

    public function test_test_connection_uses_probe(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->mock(ImapProbe::class, function ($mock): void {
            $mock->shouldReceive('probe')->once()->andReturn(['ok' => true, 'detail' => 'Connected.']);
        });

        $this->actingAs($user)
            ->postJson(route('mail.accounts.test', $account->id))
            ->assertOk()
            ->assertJson(['ok' => true, 'detail' => 'Connected.'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_sync_dispatches_producer_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('mail.accounts.sync', $account->id))
            ->assertOk()->assertJson(['dispatched' => true]);

        Queue::assertPushed(SyncMailAccount::class);
    }
}
