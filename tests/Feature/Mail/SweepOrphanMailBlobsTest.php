<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `mail:sweep-orphans` frees a mail blob (bytes + ledger row) only once its
 * MailBlob row is older than the grace window AND no MailMessage still
 * references its id — never a blob a message row still points at, and never
 * one still inside the grace window (a fresh row could be mid-transaction
 * with its MailMessage commit a moment away).
 */
class SweepOrphanMailBlobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(User $user): MailAccount
    {
        return MailAccount::factory()->create(['user_id' => $user->id]);
    }

    private function makeMessage(MailAccount $account, string $blobId): MailMessage
    {
        $message = new MailMessage([
            'id' => $blobId,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => hash('sha256', $blobId),
            'size' => 25,
            'sealed_key' => '{"suite":1,"epk":"x","kem_ct":"x","c":"x","n":"x"}',
            'created_at' => now(),
        ]);
        $message->user_id = $account->user_id;
        $message->save();

        return $message;
    }

    public function test_it_frees_an_unreferenced_blob_older_than_the_grace(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));
        $user = User::factory()->create();

        $blobId = (string) Str::uuid();
        $disk->put('mail/'.$blobId, 'sealed-ciphertext');
        MailBlob::create(['blob' => $blobId, 'user_id' => $user->id, 'size' => 18, 'created_at' => now()->subHours(48)]);
        // No MailMessage row references it (e.g. the account that owned it was
        // deleted — mail_messages cascades on account_id, mail_blobs does not).

        $this->artisan('mail:sweep-orphans')->assertSuccessful();

        $disk->assertMissing('mail/'.$blobId);
        $this->assertNull(MailBlob::query()->where('blob', $blobId)->first());
    }

    public function test_it_keeps_a_blob_a_message_still_references(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));
        $user = User::factory()->create();
        $account = $this->makeAccount($user);

        $blobId = (string) Str::uuid();
        $disk->put('mail/'.$blobId, 'sealed-ciphertext');
        MailBlob::create(['blob' => $blobId, 'user_id' => $user->id, 'size' => 18, 'created_at' => now()->subHours(48)]);
        $this->makeMessage($account, $blobId);

        $this->artisan('mail:sweep-orphans')->assertSuccessful();

        $disk->assertExists('mail/'.$blobId);
        $this->assertNotNull(MailBlob::query()->where('blob', $blobId)->first());
    }

    public function test_it_keeps_a_fresh_unreferenced_blob_within_the_grace(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));
        $user = User::factory()->create();

        $blobId = (string) Str::uuid();
        $disk->put('mail/'.$blobId, 'sealed-ciphertext');
        MailBlob::create(['blob' => $blobId, 'user_id' => $user->id, 'size' => 18, 'created_at' => now()]);

        $this->artisan('mail:sweep-orphans')->assertSuccessful();

        $disk->assertExists('mail/'.$blobId);
        $this->assertNotNull(MailBlob::query()->where('blob', $blobId)->first());
    }
}
