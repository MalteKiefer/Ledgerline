<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailPgpKey;
use App\Support\Mail\PgpDecryptor;
use App\Support\Mail\SmimeDecryptor;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Detects whether a raw archived message is PGP- or S/MIME-encrypted and, when a
 * matching user key exists, decrypts it server-side (transient plaintext only —
 * the archived raw .eml is never rewritten). Returns a DecryptOutcome carrying
 * the encrypted type, a status (nokey|fail|ok) and, on success, the plaintext.
 *
 * Detection:
 *   - PGP/MIME  : Content-Type multipart/encrypted; the ciphertext is the
 *                 application/octet-stream part (decrypts to a full MIME message).
 *   - inline PGP: a `-----BEGIN PGP MESSAGE-----` armored block in the body
 *                 (decrypts to bare text).
 *   - S/MIME    : Content-Type (x-)pkcs7-mime enveloped-data (openssl decrypts
 *                 the whole message to a full MIME message).
 */
class MailDecryptor
{
    public function __construct(
        private readonly PgpDecryptor $pgp = new PgpDecryptor,
        private readonly SmimeDecryptor $smime = new SmimeDecryptor,
    ) {}

    public function attempt(string $raw, int $userId): DecryptOutcome
    {
        $message = (new MailMimeParser)->parse($raw, true);
        $contentType = strtolower($message->getContentType());

        $isPgpMime = str_contains($contentType, 'multipart/encrypted');
        $isSmime = str_contains($contentType, 'pkcs7-mime');
        $hasInlinePgp = str_contains($raw, '-----BEGIN PGP MESSAGE-----');

        if ($isSmime) {
            return $this->decryptSmime($raw, $userId);
        }
        if ($isPgpMime || $hasInlinePgp) {
            return $this->decryptPgp($message, $raw, $userId, $isPgpMime);
        }

        return DecryptOutcome::none();
    }

    private function decryptPgp(IMessage $message, string $raw, int $userId, bool $isPgpMime): DecryptOutcome
    {
        $keys = MailPgpKey::query()->where('user_id', $userId)->where('type', 'pgp')->get();
        if ($keys->isEmpty()) {
            return DecryptOutcome::nokey('pgp');
        }

        $ciphertext = $isPgpMime ? $this->pgpMimeCiphertext($message) : null;
        if ($ciphertext === null || $ciphertext === '') {
            // Inline (or a PGP/MIME whose part we couldn't isolate): fall back to
            // the armored block scanned straight from the raw bytes.
            $ciphertext = $this->inlineArmor($raw);
        }
        if ($ciphertext === null) {
            return DecryptOutcome::failed('pgp');
        }

        foreach ($keys as $key) {
            $plaintext = $this->pgp->decrypt((string) $key->private_key, $key->passphrase, $ciphertext);
            if ($plaintext !== null && $plaintext !== '') {
                return DecryptOutcome::ok('pgp', $plaintext, $isPgpMime);
            }
        }

        return DecryptOutcome::failed('pgp');
    }

    private function decryptSmime(string $raw, int $userId): DecryptOutcome
    {
        $keys = MailPgpKey::query()->where('user_id', $userId)->where('type', 'smime')->get();
        if ($keys->isEmpty()) {
            return DecryptOutcome::nokey('smime');
        }

        foreach ($keys as $key) {
            $plaintext = $this->smime->decrypt((string) $key->private_key, (string) $key->cert_pem, $raw);
            if ($plaintext !== null && $plaintext !== '') {
                return DecryptOutcome::ok('smime', $plaintext, true);
            }
        }

        return DecryptOutcome::failed('smime');
    }

    /** The application/octet-stream ciphertext part of a PGP/MIME message. */
    private function pgpMimeCiphertext(IMessage $message): ?string
    {
        foreach ($message->getAllAttachmentParts() as $part) {
            if ($part instanceof IMessagePart
                && str_contains(strtolower($part->getContentType()), 'application/octet-stream')) {
                $stream = $part->getBinaryContentStream();
                if ($stream !== null) {
                    return $stream->getContents();
                }
            }
        }

        return null;
    }

    /** The first `-----BEGIN PGP MESSAGE-----` armored block in the raw bytes. */
    private function inlineArmor(string $raw): ?string
    {
        if (preg_match('/-----BEGIN PGP MESSAGE-----.*?-----END PGP MESSAGE-----/s', $raw, $m) === 1) {
            return $m[0];
        }

        return null;
    }
}
