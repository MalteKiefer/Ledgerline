<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Support\Mail\MailSealer;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * MailSealer shells the real Node reference sealer (resources/js/mail-sealer/
 * seal.mjs) — the same module resources/js/mail-sealer/__tests__/seal-interop
 * .test.js asserts is byte-exact interoperable with the browser client's
 * crypto. These tests therefore exercise the REAL node binary (no fakes), and
 * the strongest assertion — full round-trip decrypt with the real client
 * crypto (hybridUnwrap + ShareCrypto.decrypt) — is done by shelling two small
 * helper scripts under tests/Unit/Mail/support/ that import the exact same
 * resources/js modules the browser ships. If PHP's framing/parsing ever
 * drifted from what seal.mjs actually emits, or if the sealer's crypto ever
 * drifted from what the client can open, these tests would fail.
 */
class MailSealerTest extends TestCase
{
    private const KEYGEN_SCRIPT = 'tests/Unit/Mail/support/keygen.mjs';

    private const UNWRAP_SCRIPT = 'tests/Unit/Mail/support/unwrap.mjs';

    /** @return array{pub: string, priv: string, ek: string, seed: string} */
    private function generateKeypair(): array
    {
        $process = new Process(['node', base_path(self::KEYGEN_SCRIPT)]);
        $process->setTimeout(30);
        $process->mustRun();

        $decoded = json_decode($process->getOutput(), true);
        $this->assertIsArray($decoded, 'keygen.mjs must emit a JSON object');
        $this->assertIsString($decoded['pub'] ?? null);
        $this->assertIsString($decoded['priv'] ?? null);
        $this->assertIsString($decoded['ek'] ?? null);
        $this->assertIsString($decoded['seed'] ?? null);

        return [
            'pub' => (string) $decoded['pub'],
            'priv' => (string) $decoded['priv'],
            'ek' => (string) $decoded['ek'],
            'seed' => (string) $decoded['seed'],
        ];
    }

    /**
     * Decrypt a sealed_key+blob pair with the REAL client crypto and return the plaintext bytes.
     *
     * @param  array{pub: string, priv: string, ek: string, seed: string}  $keys
     */
    private function unwrapWithClientCrypto(array $keys, string $sealedKey, string $blob): string
    {
        $sealedKeyPath = tempnam(sys_get_temp_dir(), 'llmailtest_key_');
        $blobPath = tempnam(sys_get_temp_dir(), 'llmailtest_blob_');

        try {
            file_put_contents($sealedKeyPath, $sealedKey);
            file_put_contents($blobPath, $blob);

            $process = new Process([
                'node', base_path(self::UNWRAP_SCRIPT),
                $keys['priv'], $keys['seed'], $sealedKeyPath, $blobPath,
            ]);
            $process->setTimeout(30);
            $process->mustRun();

            return $process->getOutput();
        } finally {
            @unlink($sealedKeyPath);
            @unlink($blobPath);
        }
    }

    public function test_seal_produces_a_valid_suite1_envelope_and_nonempty_blob(): void
    {
        $keys = $this->generateKeypair();
        $raw = "From: a@b.example\r\nSubject: hi\r\n\r\nbody line one\r\nbody line two\r\n";
        // seal() takes $raw by reference and nulls it on return — capture
        // whatever we still need from it BEFORE the call.
        $rawLen = strlen($raw);

        $result = (new MailSealer)->seal($raw, $keys['pub'], $keys['ek']);

        $this->assertArrayHasKey('sealed_key', $result);
        $this->assertArrayHasKey('blob', $result);

        $envelope = json_decode($result['sealed_key'], true);
        $this->assertIsArray($envelope, 'sealed_key must be valid JSON');
        $this->assertSame(1, $envelope['suite']);
        $this->assertNotEmpty($envelope['epk']);
        $this->assertNotEmpty($envelope['kem_ct']);
        $this->assertNotEmpty($envelope['c']);
        $this->assertNotEmpty($envelope['n']);

        $this->assertNotSame('', $result['blob']);
        // secretstream framing overhead (header + length-prefix + Poly1305 tag)
        // means the sealed blob is always strictly larger than the plaintext.
        $this->assertGreaterThan($rawLen, strlen($result['blob']));
    }

    public function test_reseal_of_the_same_message_yields_a_different_key_and_blob(): void
    {
        $keys = $this->generateKeypair();
        // Two independent variables with identical content: seal() takes
        // $rawMessage BY REFERENCE and scrubs it to null on return, so reusing
        // the same PHP variable across two calls would pass null the second
        // time — these must be separate variables.
        $rawA = 'the quick brown fox jumps over the lazy dog';
        $rawB = 'the quick brown fox jumps over the lazy dog';

        $sealer = new MailSealer;
        $first = $sealer->seal($rawA, $keys['pub'], $keys['ek']);
        $second = $sealer->seal($rawB, $keys['pub'], $keys['ek']);

        // Fresh per-message secretstream key + ephemeral X25519 keypair each
        // call → both the wrap envelope and the ciphertext blob must differ,
        // proving this really re-encrypts rather than caching/reusing output.
        $this->assertNotSame($first['sealed_key'], $second['sealed_key']);
        $this->assertNotSame($first['blob'], $second['blob']);
    }

    public function test_seal_round_trips_byte_exact_through_the_real_client_crypto(): void
    {
        $keys = $this->generateKeypair();
        // Binary-ish content (CRLF line endings + non-ASCII bytes), mirroring a
        // raw fetched email — the exact scenario this class exists for.
        $raw = "From: a@b.example\r\nTo: c@d.example\r\nSubject: café ✓\r\n\r\n"
            ."Body with a NUL byte here: \x00 and more text.\r\n";
        // seal() nulls $raw by reference — snapshot the expected plaintext first.
        $expected = $raw;

        $sealed = (new MailSealer)->seal($raw, $keys['pub'], $keys['ek']);
        $decrypted = $this->unwrapWithClientCrypto($keys, $sealed['sealed_key'], $sealed['blob']);

        $this->assertSame($expected, $decrypted);
    }

    public function test_seal_round_trips_a_multi_chunk_message_byte_exact(): void
    {
        $keys = $this->generateKeypair();
        // Larger than the 4 MiB secretstream chunk size so the chunk framing
        // (header + multiple length-prefixed chunks, TAG_FINAL on the last) is
        // genuinely exercised, not just the single-chunk path.
        $raw = str_repeat('chunked mail archive payload ✓ ', 200000);
        $this->assertGreaterThan(4 * 1024 * 1024, strlen($raw));
        $expected = $raw;

        $sealed = (new MailSealer)->seal($raw, $keys['pub'], $keys['ek']);
        $decrypted = $this->unwrapWithClientCrypto($keys, $sealed['sealed_key'], $sealed['blob']);

        $this->assertSame($expected, $decrypted);
    }

    public function test_seal_round_trips_an_empty_message(): void
    {
        $keys = $this->generateKeypair();
        $raw = '';

        $sealed = (new MailSealer)->seal($raw, $keys['pub'], $keys['ek']);
        $decrypted = $this->unwrapWithClientCrypto($keys, $sealed['sealed_key'], $sealed['blob']);

        $this->assertSame('', $decrypted);
    }

    public function test_seal_throws_on_an_invalid_recipient_key_instead_of_failing_silently(): void
    {
        $keys = $this->generateKeypair();
        // Valid base64, but far too short to be a real X25519 public key —
        // node's crypto_scalarmult rejects it and exits non-zero.
        $bogusPub = base64_encode('short');
        $raw = 'does not matter';

        $this->expectException(RuntimeException::class);

        (new MailSealer)->seal($raw, $bogusPub, $keys['ek']);
    }

    public function test_seal_scrubs_the_raw_message_argument_after_returning(): void
    {
        $keys = $this->generateKeypair();
        $raw = 'plaintext that must not survive in memory after seal() returns';

        (new MailSealer)->seal($raw, $keys['pub'], $keys['ek']);

        // $rawMessage is taken by reference specifically so sodium_memzero's
        // scrub is caller-visible, not just local to seal(): after the call
        // the caller's own variable must no longer hold the plaintext. Routed
        // through opaque() so this is a genuine runtime assertion rather than
        // something PHPStan's own @param-out narrowing has already proven
        // statically (which it flags as a redundant assertion).
        $this->assertNull($this->opaque($raw));
    }

    public function test_seal_scrubs_the_raw_message_argument_even_when_it_throws(): void
    {
        $keys = $this->generateKeypair();
        $bogusPub = base64_encode('short');
        $raw = 'plaintext that must not survive an error path either';

        try {
            (new MailSealer)->seal($raw, $bogusPub, $keys['ek']);
            $this->fail('expected seal() to throw for an invalid recipient key');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull($this->opaque($raw));
    }

    /**
     * Widens a value to `mixed` so a PHPStan `@param-out` narrowing on the
     * caller side (e.g. MailSealer::seal()'s documented "$rawMessage is always
     * null afterwards") doesn't make a subsequent assertion look statically
     * redundant. The assertions that route through this are still real,
     * executed runtime checks — this only defeats PHPStan's static proof so
     * the test keeps its value as a regression guard.
     */
    private function opaque(mixed $value): mixed
    {
        return $value;
    }
}
