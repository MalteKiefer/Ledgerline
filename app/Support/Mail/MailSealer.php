<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\BinaryProcess;
use RuntimeException;

/**
 * Seals a raw fetched email to a user's public identity keys by shelling the
 * Node reference sealer (`resources/js/mail-sealer/seal.mjs`) — see that file
 * for the full crypto rationale. This class is a thin, fail-closed process
 * wrapper: PHP itself never touches key material or performs crypto.
 *
 * Zero-knowledge boundary: this class is handed the recipient's PUBLIC X25519
 * key + ML-KEM-768 encapsulation key ONLY (never a secret key — the server
 * cannot decrypt what it seals). The raw plaintext message is transient: it
 * exists only for the duration of the `seal()` call. Nothing is written to
 * disk, cached, or logged.
 *
 * Memory-hygiene scope (be precise — do not overstate): `seal()` scrubs the
 * copy IT owns (`$rawMessage`, via `sodium_memzero` in a `finally`). It does
 * NOT and cannot scrub every transient copy: Symfony Process's `AbstractPipes`
 * buffers the stdin string internally (`$inputBuffer`, progressively substr'd
 * as it is written to the child) and the Node child holds the bytes in its own
 * heap while sealing — neither is reachable from here. So a transient plaintext
 * copy unavoidably exists in the Process stdin buffer + the Node process memory
 * for the lifetime of the seal, then is released to the GC/OS on exit. This is
 * the same accepted transient-plaintext window as `/gallery/process` and
 * `/invoices/ocr` (opt-in server-side handling of decrypted bytes that never
 * lands at rest); the feature's Security-Register entry documents it. In-app
 * copies WE hold are scrubbed; process-boundary copies are not.
 *
 * The Node process is invoked via `Support\BinaryProcess` with an array-argv
 * command (no shell string — no injection risk) and the raw message piped on
 * stdin. Its stdout is framed as `<sealedKey JSON>\n<blob bytes>` — the first
 * line (up to the first 0x0A byte) is the sealedKey, everything after that
 * first newline is the raw binary sealed blob. We split on the FIRST 0x0A
 * only: the blob is binary and may itself contain 0x0A bytes.
 *
 * Failure handling is deliberately fail-closed: any error (missing node/
 * sealer, non-zero exit, malformed/truncated output) throws a
 * `RuntimeException` rather than returning a degraded result. The caller (the
 * mail sync job) MUST NOT advance its per-mailbox sync watermark past a
 * message whose seal() call threw — otherwise a transient failure would
 * silently drop that message from the archive forever.
 */
final class MailSealer
{
    /**
     * Per-message timeout for the Node sealer process. Sealing is CPU-bound
     * local crypto over the message bytes (no network I/O), so this only
     * needs to cover pathologically large messages/attachments, not latency.
     */
    private const TIMEOUT = 60;

    private const SEALER_SCRIPT = 'resources/js/mail-sealer/seal.mjs';

    /**
     * Seal a raw message to a recipient's public identity.
     *
     * `$rawMessage` is taken BY REFERENCE and deliberately mutated: PHP strings
     * are copy-on-write, so `sodium_memzero()` on a by-VALUE parameter would
     * only scrub a local copy inside this method and leave the caller's own
     * variable (and its plaintext) fully intact. Taking it by reference means
     * the scrub is real and caller-visible — after this call returns (or
     * throws), the caller's variable is null, not just this method's copy.
     * Callers must pass a plain variable holding the fetched message, not an
     * inline expression.
     *
     * @param  string  $rawMessage  the raw plaintext message bytes (e.g. a raw
     *                              .eml) — scrubbed to null on return, by reference
     * @param  string  $x25519PubB64  recipient X25519 public key, base64
     * @param  string  $mlkemEkB64  recipient ML-KEM-768 encapsulation key, base64
     *
     * @param-out  null  $rawMessage  always null afterwards — sodium_memzero()
     *                                runs unconditionally in `finally`, on both
     *                                the success and the throw path.
     *
     * @return array{sealed_key: string, blob: string} sealed_key = JSON suite-1
     *                                                 hybridWrap envelope over the
     *                                                 per-message key; blob = the
     *                                                 raw secretstream-sealed bytes
     *
     * @throws RuntimeException on any sealing failure (node missing, non-zero
     *                          exit, timeout, or malformed/truncated output) —
     *                          never a silent partial result.
     */
    public function seal(string &$rawMessage, string $x25519PubB64, string $mlkemEkB64): array
    {
        try {
            $out = BinaryProcess::run(
                ['node', base_path(self::SEALER_SCRIPT), $x25519PubB64, $mlkemEkB64],
                self::TIMEOUT,
                $rawMessage,
            );

            if ($out === null) {
                throw new RuntimeException('MailSealer: node seal.mjs failed (missing binary, non-zero exit, or timeout).');
            }

            return $this->parseFramedOutput($out);
        } finally {
            // Scrub OUR copy of the plaintext regardless of outcome. Note this
            // only clears the buffer this method owns — the Symfony Process
            // stdin buffer + the Node child's heap hold their own transient
            // copies we cannot reach (see the class docblock).
            sodium_memzero($rawMessage);
        }
    }

    /**
     * Split the CLI's `<sealedKey JSON>\n<blob bytes>` stdout framing on the
     * FIRST 0x0A byte only, and sanity-check the sealedKey envelope shape.
     *
     * @return array{sealed_key: string, blob: string}
     *
     * @throws RuntimeException if the framing or envelope is malformed
     */
    private function parseFramedOutput(string $out): array
    {
        $nl = strpos($out, "\n");
        if ($nl === false || $nl === 0) {
            throw new RuntimeException('MailSealer: malformed sealer output (missing sealed_key line).');
        }

        $sealedKey = substr($out, 0, $nl);
        $blob = substr($out, $nl + 1);

        if ($blob === '') {
            throw new RuntimeException('MailSealer: sealer produced an empty blob.');
        }

        $envelope = json_decode($sealedKey, true);
        if (! is_array($envelope)
            || ($envelope['suite'] ?? null) !== 1
            || ! is_string($envelope['epk'] ?? null) || $envelope['epk'] === ''
            || ! is_string($envelope['kem_ct'] ?? null) || $envelope['kem_ct'] === ''
            || ! is_string($envelope['c'] ?? null) || $envelope['c'] === ''
            || ! is_string($envelope['n'] ?? null) || $envelope['n'] === ''
        ) {
            throw new RuntimeException('MailSealer: sealed_key is not a valid suite:1 envelope.');
        }

        return ['sealed_key' => $sealedKey, 'blob' => $blob];
    }
}
