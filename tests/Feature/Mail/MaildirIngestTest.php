<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\Mail\IngestStatus;
use App\Services\Mail\MaildirIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The loss-safety contract for the mail archive's ingest core. Every test here
 * proves that a bug can never LOSE mail: idempotency (no re-store, no duplicate),
 * crash-safety (blob-first ordering, file left on a mid-flight failure),
 * no-identity-key handling (leave the file for retry), plaintext shredding, and
 * malformed-file quarantine that never aborts the folder. The final test proves
 * the ingestor stores something the real client crypto can actually decrypt.
 *
 * Identity keys are generated once by the SAME Node helper MailSealerTest uses
 * (keygen.mjs), so the seal path exercised is the real one, not a fake.
 */
class MaildirIngestTest extends TestCase
{
    use RefreshDatabase;

    private const KEYGEN_SCRIPT = 'tests/Unit/Mail/support/keygen.mjs';

    private const UNWRAP_SCRIPT = 'tests/Unit/Mail/support/unwrap.mjs';

    /** @var array{pub:string, priv:string, ek:string, seed:string}|null */
    private static ?array $keysCache = null;

    /** @var list<string> temp Maildir roots to clean up */
    private array $tempRoots = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->rrmdir($root);
        }
        parent::tearDown();
    }

    // ---- Step 1: idempotency + shred -------------------------------------

    public function test_ingest_is_idempotent_and_shreds_plaintext(): void
    {
        [, $account] = $this->accountWithIdentity();
        $path = $this->fixtureEml($account, "Message-Id: <x@y>\r\nSubject: t\r\n\r\nbody");

        $r1 = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);
        $this->assertTrue($r1->stored);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('mail_blobs', 1);
        $this->assertFileDoesNotExist($path); // plaintext shredded

        // Re-ingest the SAME content (a fresh file) → dedup, no duplicate, no delete.
        $path2 = $this->fixtureEml($account, "Message-Id: <x@y>\r\nSubject: t\r\n\r\nbody");
        $r2 = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path2);
        $this->assertFalse($r2->stored);
        $this->assertSame(IngestStatus::Duplicate, $r2->status);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('mail_blobs', 1);
        // The confirmed duplicate's Maildir copy is unlinked too.
        $this->assertFileDoesNotExist($path2);
    }

    public function test_a_successful_ingest_shreds_the_maildir_file(): void
    {
        [, $account] = $this->accountWithIdentity();
        $path = $this->fixtureEml($account, "Subject: shred me\r\n\r\ncontent");
        $this->assertFileExists($path);

        app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertFileDoesNotExist($path);
    }

    // ---- Step 2: atomic / crash-safe -------------------------------------

    public function test_ledger_failure_after_blob_write_leaves_the_file_and_reingest_recovers(): void
    {
        [, $account] = $this->accountWithIdentity();
        $path = $this->fixtureEml($account, "Subject: crash\r\n\r\nhalf-written");
        $hash = hash('sha256', (string) file_get_contents($path));

        // Force the ledger (MailMessage) write to fail AFTER the blob is written.
        // This runs INSIDE the ingestor's DB::transaction, so it rolls back both
        // ledger rows; the blob bytes on disk are the orphan the sweep reclaims.
        $boom = true;
        MailMessage::creating(function (MailMessage $m) use (&$boom, $hash): void {
            if ($boom && $m->content_hash === $hash) {
                throw new RuntimeException('forced ledger failure');
            }
        });

        try {
            app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);
            $this->fail('expected the forced ledger failure to propagate');
        } catch (RuntimeException) {
            // expected — a store failure must never be swallowed
        }

        // Nothing committed, but the Maildir file MUST survive for a retry.
        $this->assertFileExists($path);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('mail_blobs', 0);
        // Blob-first ordering: the orphan sealed bytes were written to disk.
        $this->assertGreaterThanOrEqual(1, count(Storage::disk(config('files.disk'))->files('mail')));

        // A clean re-ingest (failure removed) stores exactly one row.
        $boom = false;
        $r = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);
        $this->assertTrue($r->stored);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertFileDoesNotExist($path);
    }

    // ---- Step 3: no identity key -----------------------------------------

    public function test_missing_identity_key_leaves_the_file_and_stores_nothing(): void
    {
        $user = User::factory()->create(); // no published identity keys
        $this->assertNull($user->x25519_public_key);
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $path = $this->fixtureEml($account, "Subject: unsealed\r\n\r\ncannot seal yet");

        $r = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $this->assertSame(IngestStatus::NotSealable, $r->status);
        $this->assertFalse($r->stored);
        $this->assertFileExists($path);          // nothing lost — kept for retry
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('mail_blobs', 0);
        $this->assertCount(0, Storage::disk(config('files.disk'))->files('mail'));

        // Once the owner publishes identity keys, a retry archives it.
        $keys = self::identityKeys();
        $user->forceFill(['x25519_public_key' => $keys['pub'], 'mlkem_public_key' => $keys['ek']])->save();

        $r2 = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);
        $this->assertTrue($r2->stored);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertFileDoesNotExist($path);
    }

    // ---- Step 5: malformed / unreadable file ------------------------------

    public function test_unreadable_file_is_quarantined_and_stores_nothing(): void
    {
        [, $account] = $this->accountWithIdentity();
        $root = $this->maildirRoot();
        $missing = $root.'/cur/does-not-exist.eml';

        $r = app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $missing);

        $this->assertSame(IngestStatus::Quarantined, $r->status);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('mail_blobs', 0);
    }

    public function test_a_failing_file_never_aborts_the_folder_and_others_are_archived(): void
    {
        [, $account] = $this->accountWithIdentity();
        $root = $this->maildirRoot();

        // One file whose ledger write throws; two healthy files around it.
        $poison = $this->emlInto($root, 'cur', "Subject: poison\r\n\r\nfails to store");
        $good1 = $this->emlInto($root, 'cur', "Subject: good one\r\n\r\nalpha");
        $good2 = $this->emlInto($root, 'new', "Subject: good two\r\n\r\nbeta");
        $poisonHash = hash('sha256', (string) file_get_contents($poison));

        MailMessage::creating(function (MailMessage $m) use ($poisonHash): void {
            if ($m->content_hash === $poisonHash) {
                throw new RuntimeException('forced failure on the poison message');
            }
        });

        $summary = app(MaildirIngestor::class)->ingestFolder($account, 'INBOX', $root);

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(2, $summary['stored']);
        $this->assertDatabaseCount('mail_messages', 2);

        // The poison file was NOT unlinked (left for retry); the good ones were.
        $this->assertFileExists($poison);
        $this->assertFileDoesNotExist($good1);
        $this->assertFileDoesNotExist($good2);
    }

    // ---- Step 6: round-trip interop (proves the client can read it) -------

    public function test_stored_blob_round_trips_byte_exact_through_the_client_crypto(): void
    {
        $keys = self::identityKeys();
        $user = User::factory()->create();
        $user->forceFill(['x25519_public_key' => $keys['pub'], 'mlkem_public_key' => $keys['ek']])->save();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        // CRLF + non-ASCII + a NUL byte: the exact byte-fidelity mail demands.
        $original = "From: a@b.example\r\nTo: c@d.example\r\nSubject: café ✓\r\n\r\n"
            ."Body with a NUL: \x00 and more.\r\n";
        $path = $this->fixtureEml($account, $original);

        app(MaildirIngestor::class)->ingestFile($account, 'INBOX', $path);

        $message = MailMessage::query()->firstOrFail();
        $blob = MailBlob::query()->firstOrFail();
        $sealedBytes = Storage::disk(config('files.disk'))->get('mail/'.$blob->blob);
        $this->assertNotNull($sealedBytes);

        $decrypted = $this->unwrapWithClientCrypto($keys, (string) $message->sealed_key, $sealedBytes);

        $this->assertSame($original, $decrypted);
    }

    // ---- helpers ----------------------------------------------------------

    /** @return array{0: User, 1: MailAccount} */
    private function accountWithIdentity(): array
    {
        $keys = self::identityKeys();
        $user = User::factory()->create();
        $user->forceFill(['x25519_public_key' => $keys['pub'], 'mlkem_public_key' => $keys['ek']])->save();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        return [$user, $account];
    }

    private function fixtureEml(MailAccount $account, string $content): string
    {
        return $this->emlInto($this->maildirRoot(), 'cur', $content);
    }

    private function emlInto(string $root, string $sub, string $content): string
    {
        $dir = $root.'/'.$sub;
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/'.Str::uuid()->toString().'.eml';
        file_put_contents($path, $content);

        return $path;
    }

    private function maildirRoot(): string
    {
        $root = sys_get_temp_dir().'/ll-maildir-'.Str::uuid()->toString();
        foreach (['cur', 'new', 'tmp'] as $sub) {
            mkdir($root.'/'.$sub, 0700, true);
        }
        $this->tempRoots[] = $root;

        return $root;
    }

    /**
     * Generate a recipient identity keypair via the same Node helper
     * MailSealerTest uses. Memoized: one node spawn per test-class run.
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

    /**
     * Decrypt a sealed_key + blob pair with the REAL client crypto (hybridUnwrap
     * + ShareCrypto.decrypt), proving the archived bytes are client-readable.
     *
     * @param  array{pub:string, priv:string, ek:string, seed:string}  $keys
     */
    private function unwrapWithClientCrypto(array $keys, string $sealedKey, string $blob): string
    {
        $keyPath = (string) tempnam(sys_get_temp_dir(), 'llmail_key_');
        $blobPath = (string) tempnam(sys_get_temp_dir(), 'llmail_blob_');

        try {
            file_put_contents($keyPath, $sealedKey);
            file_put_contents($blobPath, $blob);

            $process = new Process([
                'node', base_path(self::UNWRAP_SCRIPT),
                $keys['priv'], $keys['seed'], $keyPath, $blobPath,
            ]);
            $process->setTimeout(30);
            $process->mustRun();

            return $process->getOutput();
        } finally {
            @unlink($keyPath);
            @unlink($blobPath);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $p = $dir.'/'.$entry;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
