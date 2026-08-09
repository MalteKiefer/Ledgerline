<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Result of attempting to decrypt an archived message server-side.
 *   type   — null (not encrypted) | 'pgp' | 'smime'
 *   status — null (not encrypted) | 'nokey' | 'fail' | 'ok'
 *   plaintext — the decrypted content when status='ok', else null
 *   isMime — whether the plaintext is a full RFC822/MIME message (re-parse it)
 *            or bare text (inline PGP → use directly as the text body)
 */
final readonly class DecryptOutcome
{
    public function __construct(
        public ?string $type,
        public ?string $status,
        public ?string $plaintext = null,
        public bool $isMime = false,
    ) {}

    public static function none(): self
    {
        return new self(null, null);
    }

    public static function nokey(string $type): self
    {
        return new self($type, 'nokey');
    }

    public static function failed(string $type): self
    {
        return new self($type, 'fail');
    }

    public static function ok(string $type, string $plaintext, bool $isMime): self
    {
        return new self($type, 'ok', $plaintext, $isMime);
    }
}
