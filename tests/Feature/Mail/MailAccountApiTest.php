<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Account CRUD + status + on-demand sync + the message ledger, over
 * /api/v1/mail — metadata only, zero-knowledge preserving. The account
 * password is the one plaintext secret the server holds (to run the IMAP
 * connection); it must never round-trip back to any client in a JSON
 * response. `module:mail` is a registered key in config/modules.php (see
 * ModulePermissionsTest for the general gate mechanics); the module-specific
 * gate assertions live at the bottom of this file.
 */
class MailAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device')->plainTextToken];
    }

    private function makeMessage(MailAccount $account, string $hash, ?string $folder = null): MailMessage
    {
        $message = new MailMessage([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'folder' => $folder ?? 'INBOX',
            'content_hash' => $hash,
            'size' => 2048,
            'sealed_key' => '{"suite":1,"epk":"x","kem_ct":"x","c":"x","n":"x"}',
            'created_at' => now(),
        ]);
        $message->user_id = $account->user_id;
        $message->save();

        return $message;
    }

    // ---- create -------------------------------------------------------

    public function test_creating_an_account_stores_the_password_but_never_returns_it(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'super-secret-imap-pw',
                'encryption' => 'ssl',
            ])
            ->assertCreated();

        $response->assertJsonMissingPath('account.password');
        $this->assertStringNotContainsString('super-secret-imap-pw', $response->getContent() ?: '');

        $account = MailAccount::query()->firstOrFail();
        $this->assertSame($user->id, $account->user_id);
        $this->assertSame('super-secret-imap-pw', $account->password);
        $this->assertSame('Work', $account->name);
        $this->assertSame('idle', $account->status);
    }

    public function test_delete_after_import_persists_and_is_returned(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'pw',
                'encryption' => 'ssl',
                'delete_after_import' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('account.delete_after_import', true);

        $account = MailAccount::query()->firstOrFail();
        $this->assertTrue($account->delete_after_import);

        // Defaults to false when omitted.
        $this->withHeaders($this->bearer($user))
            ->putJson("/api/v1/mail/accounts/{$account->id}", [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'encryption' => 'ssl',
                'delete_after_import' => false,
            ])
            ->assertOk()
            ->assertJsonPath('account.delete_after_import', false);

        $this->assertFalse($account->fresh()->delete_after_import);
    }

    public function test_sync_interval_persists_and_null_falls_back_to_default(): void
    {
        config(['mail_archive.sync_interval_minutes' => 30]);
        $user = User::factory()->create();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'pw',
                'encryption' => 'ssl',
                'sync_interval_minutes' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('account.sync_interval_minutes', 5);

        $account = MailAccount::query()->firstOrFail();
        $this->assertSame(5, $account->sync_interval_minutes);
        $this->assertSame(5, $account->effectiveSyncIntervalMinutes());

        // Clearing the override falls back to the workspace default.
        $this->withHeaders($this->bearer($user))
            ->putJson("/api/v1/mail/accounts/{$account->id}", [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'encryption' => 'ssl',
                'sync_interval_minutes' => null,
            ])
            ->assertOk()
            ->assertJsonPath('account.sync_interval_minutes', null);

        $this->assertNull($account->fresh()->sync_interval_minutes);
        $this->assertSame(30, $account->fresh()->effectiveSyncIntervalMinutes());
    }

    public function test_is_due_for_sync_honours_the_effective_interval(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create([
            'user_id' => $user->id,
            'sync_interval_minutes' => 15,
            'last_synced_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($account->isDueForSync(now()));
        $this->assertTrue($account->isDueForSync(now()->addMinutes(6)));

        // Never synced → always due.
        $account->forceFill(['last_synced_at' => null])->save();
        $this->assertTrue($account->fresh()->isDueForSync(now()));
    }

    public function test_create_stamps_the_owner_server_side_never_from_request(): void
    {
        $user = User::factory()->create();
        $intruder = User::factory()->create();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [
                'user_id' => $intruder->id,
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'secret-pw',
                'encryption' => 'ssl',
            ])
            ->assertCreated();

        $account = MailAccount::query()->firstOrFail();
        $this->assertSame($user->id, $account->user_id);
    }

    public function test_create_rejects_a_link_local_host(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [
                'name' => 'Evil',
                'host' => '169.254.169.254',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'secret-pw',
                'encryption' => 'ssl',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['host']);

        $this->assertSame(0, MailAccount::query()->count());
    }

    public function test_create_validates_required_fields_and_encryption_enum(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'host', 'port', 'username', 'password', 'encryption']);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/mail/accounts', [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'secret-pw',
                'encryption' => 'not-a-real-scheme',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['encryption']);
    }

    // ---- list -----------------------------------------------------------

    public function test_list_never_returns_the_password_and_includes_a_message_count(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'password' => 'super-secret-imap-pw']);
        $this->makeMessage($account, 'hash-1');
        $this->makeMessage($account, 'hash-2');

        $response = $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/mail/accounts')
            ->assertOk();

        $response->assertJsonMissingPath('accounts.0.password');
        $this->assertStringNotContainsString('super-secret-imap-pw', $response->getContent() ?: '');
        $response->assertJsonPath('accounts.0.id', $account->id);
        $response->assertJsonPath('accounts.0.message_count', 2);
    }

    public function test_list_only_shows_the_caller_own_accounts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        MailAccount::factory()->create(['user_id' => $owner->id]);
        MailAccount::factory()->create(['user_id' => $other->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/mail/accounts')
            ->assertOk();

        $this->assertCount(1, $response->json('accounts'));
    }

    // ---- update -----------------------------------------------------------

    public function test_update_with_a_blank_password_keeps_the_stored_one(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'password' => 'original-secret']);

        $response = $this->withHeaders($this->bearer($user))
            ->putJson("/api/v1/mail/accounts/{$account->id}", [
                'name' => 'Renamed',
                'host' => $account->host,
                'port' => $account->port,
                'username' => $account->username,
                'password' => '',
                'encryption' => $account->encryption,
            ])
            ->assertOk();

        $response->assertJsonMissingPath('account.password');

        $fresh = $account->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('original-secret', $fresh->password);
        $this->assertSame('Renamed', $fresh->name);
    }

    public function test_update_with_a_new_password_changes_the_stored_one(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'password' => 'original-secret']);

        $this->withHeaders($this->bearer($user))
            ->putJson("/api/v1/mail/accounts/{$account->id}", [
                'name' => $account->name,
                'host' => $account->host,
                'port' => $account->port,
                'username' => $account->username,
                'password' => 'brand-new-secret',
                'encryption' => $account->encryption,
            ])
            ->assertOk();

        $fresh = $account->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('brand-new-secret', $fresh->password);
    }

    public function test_update_also_rejects_a_link_local_host(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->bearer($user))
            ->putJson("/api/v1/mail/accounts/{$account->id}", [
                'name' => $account->name,
                'host' => 'fe80::1',
                'port' => $account->port,
                'username' => $account->username,
                'password' => '',
                'encryption' => $account->encryption,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['host']);
    }

    public function test_a_different_user_cannot_update_the_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders($this->bearer($other))
            ->putJson("/api/v1/mail/accounts/{$account->id}", [
                'name' => 'Hijacked',
                'host' => $account->host,
                'port' => $account->port,
                'username' => $account->username,
                'password' => '',
                'encryption' => $account->encryption,
            ])
            ->assertNotFound();
    }

    // ---- destroy -----------------------------------------------------------

    public function test_owner_can_delete_the_account(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->bearer($user))
            ->deleteJson("/api/v1/mail/accounts/{$account->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('mail_accounts', ['id' => $account->id]);
    }

    public function test_a_different_user_cannot_delete_the_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders($this->bearer($other))
            ->deleteJson("/api/v1/mail/accounts/{$account->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('mail_accounts', ['id' => $account->id]);
    }

    // ---- sync -----------------------------------------------------------

    public function test_sync_dispatches_the_sync_job_for_the_account(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->bearer($user))
            ->postJson("/api/v1/mail/accounts/{$account->id}/sync")
            ->assertOk();

        Bus::assertDispatched(SyncMailAccount::class, fn (SyncMailAccount $job): bool => $job->accountId === $account->id);
    }

    public function test_a_different_user_cannot_trigger_a_sync(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders($this->bearer($other))
            ->postJson("/api/v1/mail/accounts/{$account->id}/sync")
            ->assertNotFound();

        Bus::assertNotDispatched(SyncMailAccount::class);
    }

    public function test_cancel_settles_the_account_to_idle_and_clears_the_batch(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create([
            'user_id' => $user->id,
            'status' => 'syncing',
            'sync_batch_id' => 'no-such-batch',
        ]);

        $this->withHeaders($this->bearer($user))
            ->postJson("/api/v1/mail/accounts/{$account->id}/sync/cancel")
            ->assertOk()
            ->assertJson(['cancelled' => true]);

        $account->refresh();
        $this->assertSame('idle', $account->status);
        $this->assertNull($account->sync_batch_id);
    }

    public function test_a_different_user_cannot_cancel_a_sync(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id, 'status' => 'syncing']);

        $this->withHeaders($this->bearer($other))
            ->postJson("/api/v1/mail/accounts/{$account->id}/sync/cancel")
            ->assertNotFound();

        $this->assertSame('syncing', $account->refresh()->status);
    }

    // ---- status -----------------------------------------------------------

    public function test_status_returns_counts_and_state(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create([
            'user_id' => $user->id,
            'status' => 'error',
            'last_error' => 'connection refused',
        ]);
        $this->makeMessage($account, 'hash-1');
        $this->makeMessage($account, 'hash-2');
        $this->makeMessage($account, 'hash-3');

        $response = $this->withHeaders($this->bearer($user))
            ->getJson("/api/v1/mail/accounts/{$account->id}/status")
            ->assertOk();

        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('last_error', 'connection refused');
        $response->assertJsonPath('message_count', 3);
    }

    public function test_a_different_user_cannot_read_status(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders($this->bearer($other))
            ->getJson("/api/v1/mail/accounts/{$account->id}/status")
            ->assertNotFound();
    }

    // ---- messages ledger -----------------------------------------------------------

    public function test_messages_ledger_returns_rows_with_no_content(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->makeMessage($account, 'hash-1', 'INBOX');

        $response = $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/mail/messages')
            ->assertOk();

        $response->assertJsonPath('data.0.id', $message->id);
        $response->assertJsonPath('data.0.account_id', $account->id);
        $response->assertJsonPath('data.0.folder', 'INBOX');
        $response->assertJsonPath('data.0.size', 2048);
        $response->assertJsonPath('data.0.sealed_key', '{"suite":1,"epk":"x","kem_ct":"x","c":"x","n":"x"}');
        $response->assertJsonStructure(['data' => [['id', 'account_id', 'folder', 'size', 'created_at', 'sealed_key']], 'meta']);

        // No content field of any kind — the server never sees plaintext.
        $response->assertJsonMissingPath('data.0.subject');
        $response->assertJsonMissingPath('data.0.from');
        $response->assertJsonMissingPath('data.0.body');
    }

    public function test_messages_ledger_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerAccount = MailAccount::factory()->create(['user_id' => $owner->id]);
        $otherAccount = MailAccount::factory()->create(['user_id' => $other->id]);
        $this->makeMessage($ownerAccount, 'owner-hash');
        $this->makeMessage($otherAccount, 'other-hash');

        $response = $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/mail/messages')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.account_id', $ownerAccount->id);
    }

    public function test_messages_ledger_can_be_filtered_by_account(): void
    {
        $user = User::factory()->create();
        $accountA = MailAccount::factory()->create(['user_id' => $user->id]);
        $accountB = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->makeMessage($accountA, 'a-hash');
        $this->makeMessage($accountB, 'b-hash');

        $response = $this->withHeaders($this->bearer($user))
            ->getJson("/api/v1/mail/messages?account_id={$accountA->id}")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.account_id', $accountA->id);
    }

    // ---- module gate -----------------------------------------------------------

    /**
     * `mail` is registered in config/modules.php (Task 9) — EnsureModule now
     * actually enforces it: a user whose allow-list excludes `mail` gets 403 on
     * every /api/v1/mail/* route; a user who has it (or an unrestricted
     * allow-list) passes through unaffected.
     */
    public function test_module_mail_gate_blocks_a_user_without_the_mail_module(): void
    {
        $this->assertArrayHasKey('mail', (array) config('modules.list', []));

        $user = User::factory()->create();
        $user->forceFill(['modules' => ['dashboard']])->save();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/mail/accounts')
            ->assertForbidden();

        $this->withHeaders($this->bearer($user))
            ->getJson("/api/v1/mail/accounts/{$account->id}/status")
            ->assertForbidden();
    }

    public function test_module_mail_gate_allows_a_user_with_the_mail_module(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['modules' => ['dashboard', 'mail']])->save();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/mail/accounts')
            ->assertOk();

        $this->withHeaders($this->bearer($user))
            ->getJson("/api/v1/mail/accounts/{$account->id}/status")
            ->assertOk();
    }
}
