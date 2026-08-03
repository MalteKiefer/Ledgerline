<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\Mail\IngestMailChunk;
use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use App\Models\User;
use App\Services\Mail\MaildirIngestor;
use App\Services\Mail\MbsyncResult;
use App\Services\Mail\MbsyncRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Feature\Mail\Support\FakeMbsyncRunner;
use Tests\TestCase;

/**
 * End-to-end loss-safety + resumability of the sync pipeline (Task 7):
 * SyncMailAccount (producer) → mbsync (faked) → chunked IngestMailChunk workers
 * → the REAL MaildirIngestor. The fake only substitutes the network/mbsync
 * step; sealing, dedup, ledgering and shredding are the production code.
 *
 * Resumability is anchored in durable state — the Maildir files that mbsync
 * leaves + the ledger's content-hash dedup — never in the transient job batch:
 * a re-dispatch stores zero new rows; a mid-flight failure is re-ingestable.
 *
 * QUEUE_CONNECTION=sync (phpunit.xml), so a dispatch runs the producer AND its
 * ingest batch inline within the test — no queue worker needed.
 */
class SyncFlowTest extends TestCase
{
    use RefreshDatabase;

    private const KEYGEN_SCRIPT = 'tests/Unit/Mail/support/keygen.mjs';

    /** @var array{pub:string, priv:string, ek:string, seed:string}|null */
    private static ?array $keysCache = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
        // RefreshDatabase resets the DB but not the filesystem; the runner's
        // scratch dirs are keyed by account id and must not carry stale state.
        File::deleteDirectory(storage_path('app/mail-sync'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/mail-sync'));
        parent::tearDown();
    }

    // ---- 1: fetch + ingest end-to-end ------------------------------------

    public function test_dispatch_fetches_and_ingests_every_message(): void
    {
        [, $account] = $this->accountWithIdentity();
        $this->bindRunner(MbsyncResult::success(), fn (MailAccount $a) => $this->dropFixtures($a, 'INBOX', 5));

        SyncMailAccount::dispatch($account->id);

        $this->assertDatabaseCount('mail_messages', 5);
        $this->assertDatabaseCount('mail_blobs', 5);

        $account->refresh();
        $this->assertSame('idle', $account->status);
        $this->assertNotNull($account->last_synced_at);
        $this->assertNull($account->last_error);

        // Plaintext shredded: the fetched Maildir files are gone once archived.
        $this->assertSame([], $this->maildirEmls($account, 'INBOX'));
    }

    // ---- 2: idempotent re-dispatch ---------------------------------------

    public function test_redispatch_stores_no_new_rows(): void
    {
        [, $account] = $this->accountWithIdentity();
        // The fake re-drops the SAME 5 message contents on every run (as a
        // re-sync of already-archived mail would): dedup must store nothing new.
        $this->bindRunner(MbsyncResult::success(), fn (MailAccount $a) => $this->dropFixtures($a, 'INBOX', 5));

        SyncMailAccount::dispatch($account->id);
        $this->assertDatabaseCount('mail_messages', 5);

        SyncMailAccount::dispatch($account->id);
        $this->assertDatabaseCount('mail_messages', 5);
        $this->assertDatabaseCount('mail_blobs', 5);
    }

    // ---- 3: fetch failure → error, no ingest -----------------------------

    public function test_mbsync_failure_marks_error_and_ingests_nothing(): void
    {
        [, $account] = $this->accountWithIdentity();
        // Even though fixtures are dropped, a failed fetch must return before
        // enumerating/ingesting anything.
        $this->bindRunner(MbsyncResult::failed('IMAP sync failed.'), fn (MailAccount $a) => $this->dropFixtures($a, 'INBOX', 3));

        SyncMailAccount::dispatch($account->id);

        $this->assertDatabaseCount('mail_messages', 0);
        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertNotNull($account->last_error);
    }

    public function test_mbsync_unavailable_marks_error_and_ingests_nothing(): void
    {
        [, $account] = $this->accountWithIdentity();
        $this->bindRunner(MbsyncResult::unavailable(), fn (MailAccount $a) => $this->dropFixtures($a, 'INBOX', 3));

        SyncMailAccount::dispatch($account->id);

        $this->assertDatabaseCount('mail_messages', 0);
        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertNotNull($account->last_error);
    }

    // ---- 4: per-account no-overlap ---------------------------------------

    public function test_producer_is_guarded_by_a_per_account_overlap_lock(): void
    {
        [, $account] = $this->accountWithIdentity();

        $overlap = collect((new SyncMailAccount($account->id))->middleware())
            ->first(fn ($m) => $m instanceof WithoutOverlapping);

        $this->assertNotNull($overlap, 'SyncMailAccount must carry a WithoutOverlapping middleware.');
        $this->assertStringContainsString((string) $account->id, (string) $overlap->key);

        // Behavioural: two runs of the same account never double-store, even
        // absent a live lock (the ingestor's dedup is the second safety net).
        $this->bindRunner(MbsyncResult::success(), fn (MailAccount $a) => $this->dropFixtures($a, 'INBOX', 4));
        SyncMailAccount::dispatch($account->id);
        SyncMailAccount::dispatch($account->id);
        $this->assertDatabaseCount('mail_messages', 4);
    }

    // ---- 5: cancelled batch aborts remaining chunks ----------------------

    public function test_a_cancelled_batch_ingests_nothing(): void
    {
        [, $account] = $this->accountWithIdentity();
        $paths = [
            $this->writeEml($account, 'INBOX', "Message-Id: <c1@t>\r\nSubject: c1\r\n\r\none"),
            $this->writeEml($account, 'INBOX', "Message-Id: <c2@t>\r\nSubject: c2\r\n\r\ntwo"),
        ];

        // A chunk whose batch is already cancelled must abort at the top and
        // touch nothing — proving a user abort stops remaining work.
        [$chunk] = (new IngestMailChunk($account->id, 'INBOX', $paths))
            ->withFakeBatch(cancelledAt: Carbon::now()->toImmutable());
        $chunk->handle(app(MaildirIngestor::class));

        $this->assertDatabaseCount('mail_messages', 0);
        foreach ($paths as $p) {
            $this->assertFileExists($p); // left for a later, un-cancelled run
        }
    }

    // ---- 6: keyless owner → no fetch, no plaintext accumulates -----------

    public function test_sync_is_skipped_when_owner_has_no_identity_keys(): void
    {
        // No forceFill of x25519/mlkem keys: this owner has configured a
        // mailbox but never unlocked their vault.
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'enabled' => true]);
        $fake = $this->bindRunner(MbsyncResult::success(), fn (MailAccount $a) => $this->dropFixtures($a, 'INBOX', 3));

        SyncMailAccount::dispatch($account->id);

        // The pre-flight must stop the producer before it ever calls the
        // runner — mbsync is never invoked, so nothing is fetched and no
        // Maildir files exist to leave as unsealed plaintext.
        $this->assertSame(0, $fake->runCount, 'MbsyncRunner::run() must not be invoked for a keyless owner.');
        $this->assertSame([], $this->maildirEmls($account, 'INBOX'));
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('mail_blobs', 0);

        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertNotNull($account->last_error);

        // Once the owner unlocks their vault (keys published), a re-run
        // archives normally — the skip is a retry, not a permanent lock-out.
        $keys = self::identityKeys();
        $user->forceFill(['x25519_public_key' => $keys['pub'], 'mlkem_public_key' => $keys['ek']])->save();

        SyncMailAccount::dispatch($account->id);

        $this->assertSame(1, $fake->runCount);
        $this->assertDatabaseCount('mail_messages', 3);
        $this->assertDatabaseCount('mail_blobs', 3);
        $account->refresh();
        $this->assertSame('idle', $account->status);
        $this->assertNull($account->last_error);
    }

    // ---- helpers ----------------------------------------------------------

    private function bindRunner(MbsyncResult $result, ?\Closure $onRun = null): FakeMbsyncRunner
    {
        $fake = new FakeMbsyncRunner($result);
        $fake->onRun = $onRun;
        $this->app->instance(MbsyncRunner::class, $fake);

        return $fake;
    }

    /** Drop $count deterministic-content message files into a folder's Maildir. */
    private function dropFixtures(MailAccount $account, string $folder, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            // Deterministic content per index → re-dropping yields the same
            // content hash, so a re-sync dedups instead of duplicating.
            $this->writeEml($account, $folder, "Message-Id: <msg-{$i}@test>\r\nSubject: subject {$i}\r\n\r\nbody number {$i}");
        }
    }

    private function writeEml(MailAccount $account, string $folder, string $content): string
    {
        $dir = storage_path('app/mail-sync/'.$account->id.'/maildir/'.$folder.'/cur');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/'.Str::uuid()->toString().'.eml';
        file_put_contents($path, $content);

        return $path;
    }

    /** @return list<string> the remaining .eml files in a folder's cur/+new/ */
    private function maildirEmls(MailAccount $account, string $folder): array
    {
        $base = storage_path('app/mail-sync/'.$account->id.'/maildir/'.$folder);
        $found = [];
        foreach (['cur', 'new'] as $sub) {
            foreach (glob($base.'/'.$sub.'/*.eml') ?: [] as $f) {
                $found[] = $f;
            }
        }

        return $found;
    }

    /** @return array{0: User, 1: MailAccount} */
    private function accountWithIdentity(): array
    {
        $keys = self::identityKeys();
        $user = User::factory()->create();
        $user->forceFill(['x25519_public_key' => $keys['pub'], 'mlkem_public_key' => $keys['ek']])->save();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'enabled' => true]);

        return [$user, $account];
    }

    /**
     * Generate one recipient identity keypair via the same Node helper the
     * ingest test uses, so the real seal path is exercised. Memoized per class.
     *
     * @return array{pub:string, priv:string, ek:string, seed:string}
     */
    private static function identityKeys(): array
    {
        if (self::$keysCache !== null) {
            return self::$keysCache;
        }

        $process = new Process(['node', base_path(self::KEYGEN_SCRIPT)]);
        $process->setTimeout(30);
        $process->mustRun();

        $decoded = json_decode($process->getOutput(), true);
        \assert(is_array($decoded));

        return self::$keysCache = [
            'pub' => (string) $decoded['pub'],
            'priv' => (string) $decoded['priv'],
            'ek' => (string) $decoded['ek'],
            'seed' => (string) $decoded['seed'],
        ];
    }
}
