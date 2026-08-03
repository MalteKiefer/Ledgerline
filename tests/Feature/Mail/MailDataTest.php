<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Actions\PurgeUserAccount;
use App\Models\MailAccount;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\UserData\MailData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The MailData GDPR contributor: export lists the ciphertext blob inventory
 * for the mail archive's sealed message blobs (never plaintext), and purge
 * frees the bytes + ownership ledger row for account erasure — mirroring
 * ContactsData/InvoicesData exactly. Also covers the `mail` module gate,
 * newly activated by this task's config/modules.php registration.
 */
class MailDataTest extends TestCase
{
    use RefreshDatabase;

    // ---- export -----------------------------------------------------------

    public function test_export_lists_the_ciphertext_blob_inventory_without_plaintext(): void
    {
        $user = User::factory()->create();
        $blob = MailBlob::create([
            'blob' => (string) Str::uuid(),
            'user_id' => $user->id,
            'size' => 4096,
            'created_at' => now(),
        ]);

        $export = (new MailData)->export($user);

        $this->assertArrayHasKey('blobs', $export);
        $this->assertCount(1, $export['blobs']);
        $this->assertSame($blob->blob, $export['blobs'][0]['blob']);
        $this->assertSame(4096, $export['blobs'][0]['size']);
        $this->assertArrayHasKey('created_at', $export['blobs'][0]);

        // Only the non-secret inventory — never sealed_key/content/plaintext.
        $this->assertCount(3, $export['blobs'][0]);
    }

    public function test_export_only_lists_the_callers_own_blobs(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        MailBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $owner->id, 'size' => 1, 'created_at' => now()]);
        MailBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $other->id, 'size' => 2, 'created_at' => now()]);

        $export = (new MailData)->export($owner);

        $this->assertCount(1, $export['blobs']);
    }

    // ---- purge --------------------------------------------------------------

    public function test_purge_deletes_the_users_mail_blob_bytes_and_ledger_row_but_not_another_users(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));

        $owner = User::factory()->create();
        $other = User::factory()->create();

        $blob = (string) Str::uuid();
        $disk->put('mail/'.$blob, 'sealed-rfc822-ciphertext');
        MailBlob::create(['blob' => $blob, 'user_id' => $owner->id, 'size' => 25, 'created_at' => now()]);

        $otherBlob = (string) Str::uuid();
        $disk->put('mail/'.$otherBlob, 'keep');
        MailBlob::create(['blob' => $otherBlob, 'user_id' => $other->id, 'size' => 4, 'created_at' => now()]);

        (new MailData)->purge($owner);

        $disk->assertMissing('mail/'.$blob);
        $this->assertNull(MailBlob::query()->where('blob', $blob)->first());

        $disk->assertExists('mail/'.$otherBlob);
        $this->assertNotNull(MailBlob::query()->where('blob', $otherBlob)->first());
    }

    public function test_full_account_erasure_purges_mail_blob_bytes_via_the_contributor(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));

        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $blobId = (string) Str::uuid();
        $disk->put('mail/'.$blobId, 'sealed-rfc822-ciphertext');
        MailBlob::create(['blob' => $blobId, 'user_id' => $owner->id, 'size' => 25, 'created_at' => now()]);
        $message = new MailMessage([
            'id' => $blobId,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => hash('sha256', 'x'),
            'size' => 25,
            'sealed_key' => '{"suite":1,"epk":"x","kem_ct":"x","c":"x","n":"x"}',
            'created_at' => now(),
        ]);
        $message->user_id = $owner->id;
        $message->save();

        app(PurgeUserAccount::class)->handle($owner);

        $disk->assertMissing('mail/'.$blobId);
        $this->assertNull(MailBlob::query()->where('blob', $blobId)->first());
        // The account/message rows cascade with the user delete.
        $this->assertDatabaseMissing('mail_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('mail_messages', ['id' => $blobId]);
    }

    // ---- module gate -----------------------------------------------------------
    //
    // Two separate test methods, each making exactly one authenticated request:
    // Sanctum's guard (a Laravel RequestGuard) caches the resolved user on first
    // use, so a SECOND bearer-token request for a DIFFERENT user within the same
    // test method would incorrectly reuse the first request's resolved user
    // rather than re-authenticating — a testing-harness pitfall, not a product
    // behaviour, so it must not shape the assertions below.

    public function test_module_mail_gate_blocks_a_user_without_the_mail_module(): void
    {
        $this->assertArrayHasKey('mail', (array) config('modules.list', []));

        $blocked = User::factory()->create(['role' => 'user', 'modules' => ['dashboard']]);
        $token = $blocked->createToken('device', ['device'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/mail/accounts')
            ->assertForbidden();
    }

    public function test_module_mail_gate_allows_a_user_with_the_mail_module(): void
    {
        $allowed = User::factory()->create(['role' => 'user', 'modules' => ['dashboard', 'mail']]);
        $token = $allowed->createToken('device', ['device'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/mail/accounts')
            ->assertOk();
    }
}
