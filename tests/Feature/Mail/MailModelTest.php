<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\MailSyncState;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeMessage(MailAccount $account, string $contentHash): MailMessage
    {
        $message = new MailMessage([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => $contentHash,
            'size' => 1024,
            'sealed_key' => '{"suite":1,"epk":"x","kem_ct":"x","c":"x","n":"x"}',
            'created_at' => now(),
        ]);
        // user_id is stamped from context (AssignsOwner), not fillable-from-request —
        // set it directly, as a worker/controller would, bypassing mass assignment.
        $message->user_id = $account->user_id;

        return $message;
    }

    public function test_account_password_is_encrypted_at_rest(): void
    {
        $account = MailAccount::factory()->create(['password' => 'super-secret-imap-pw']);

        $raw = DB::table('mail_accounts')->where('id', $account->id)->value('password');
        $this->assertIsString($raw);
        $this->assertNotSame('super-secret-imap-pw', $raw);
        $this->assertStringNotContainsString('super-secret-imap-pw', $raw);

        $fresh = MailAccount::query()->findOrFail($account->id);
        $this->assertSame('super-secret-imap-pw', $fresh->password);
    }

    public function test_account_owner_is_not_mass_assignable(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        // A mass-assignment attempt from "request input" containing user_id must be
        // ignored — the column isn't in #[Fillable], and the model is guarded by
        // default. Callers stamp it via AssignsOwner or an explicit property set.
        $account = new MailAccount([
            'user_id' => $intruder->id,
            'name' => 'Work',
            'host' => 'imap.example.com',
            'port' => 993,
            'username' => 'me@example.com',
            'password' => 'secret',
            'encryption' => 'ssl',
        ]);

        $this->assertNull($account->user_id);
        $account->forceFill(['user_id' => $owner->id]);
        $account->save();

        $fresh = $account->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame($owner->id, $fresh->user_id);
    }

    public function test_message_content_hash_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->makeMessage($account, 'same-hash')->save();

        $this->expectException(QueryException::class);
        $this->makeMessage($account, 'same-hash')->save();
    }

    public function test_message_content_hash_may_repeat_across_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountA = MailAccount::factory()->create(['user_id' => $userA->id]);
        $accountB = MailAccount::factory()->create(['user_id' => $userB->id]);

        $this->makeMessage($accountA, 'shared-hash')->save();
        $this->makeMessage($accountB, 'shared-hash')->save();

        $this->assertSame(2, MailMessage::query()->where('content_hash', 'shared-hash')->count());
    }

    public function test_sync_state_composite_key_upserts_without_duplicating(): void
    {
        $account = MailAccount::factory()->create();

        MailSyncState::query()->updateOrCreate(
            ['account_id' => $account->id, 'folder' => 'INBOX'],
            ['uidvalidity' => 1, 'highest_uid' => 10],
        );
        MailSyncState::query()->updateOrCreate(
            ['account_id' => $account->id, 'folder' => 'INBOX'],
            ['uidvalidity' => 1, 'highest_uid' => 42],
        );
        // A different folder on the same account is a distinct row.
        MailSyncState::query()->updateOrCreate(
            ['account_id' => $account->id, 'folder' => 'Sent'],
            ['uidvalidity' => 1, 'highest_uid' => 3],
        );

        $this->assertSame(
            1,
            MailSyncState::query()->where('account_id', $account->id)->where('folder', 'INBOX')->count(),
        );
        $inbox = MailSyncState::query()->where('account_id', $account->id)->where('folder', 'INBOX')->first();
        $this->assertNotNull($inbox);
        $this->assertSame(42, $inbox->highest_uid);
        $this->assertSame(2, MailSyncState::query()->where('account_id', $account->id)->count());
    }

    public function test_deleting_the_user_cascades_all_mail_rows(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        MailSyncState::query()->create(['account_id' => $account->id, 'folder' => 'INBOX', 'highest_uid' => 1]);

        $message = $this->makeMessage($account, 'to-be-cascaded');
        $message->save();

        $blobId = (string) Str::uuid();
        MailBlob::query()->create([
            'blob' => $blobId,
            'user_id' => $user->id,
            'size' => 2048,
            'created_at' => now(),
        ]);

        $user->delete();

        $this->assertDatabaseMissing('mail_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('mail_sync_state', ['account_id' => $account->id]);
        $this->assertDatabaseMissing('mail_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('mail_blobs', ['blob' => $blobId]);
    }
}
