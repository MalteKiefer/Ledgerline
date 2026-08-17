<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\BinaryProcess;
use Throwable;

/**
 * Server-side OpenPGP decryption via the `gpg` binary (in the runtime image),
 * run through BinaryProcess (array-argv, no shell — no injection). Each call
 * imports the user's armored secret key into an EPHEMERAL, throwaway GNUPGHOME
 * (0700 temp dir), decrypts, and shreds the homedir in a finally — no key
 * material ever persists in a keyring. The decrypted plaintext is transient
 * (same accepted transient-plaintext posture as receipt-OCR); nothing is logged.
 */
final class PgpDecryptor
{
    private const TIMEOUT = 30;

    public function available(): bool
    {
        return BinaryProcess::available('gpg');
    }

    /**
     * Decrypt an OpenPGP message (armored or binary) with the given armored
     * secret key. Returns the plaintext, or null on any failure (missing binary,
     * wrong key, bad passphrase, malformed input).
     */
    public function decrypt(string $armoredSecretKey, ?string $passphrase, string $ciphertext): ?string
    {
        if (! $this->available() || $armoredSecretKey === '' || $ciphertext === '') {
            return null;
        }

        $result = $this->inHome(function (string $home) use ($armoredSecretKey, $passphrase, $ciphertext): ?string {
            $keyFile = $home.'/key.asc';
            $msgFile = $home.'/msg';
            file_put_contents($keyFile, $armoredSecretKey);
            file_put_contents($msgFile, $ciphertext);

            BinaryProcess::run($this->base($home, ['--import', $keyFile]), self::TIMEOUT);

            $argv = $this->base($home, ['--pinentry-mode', 'loopback']);
            if ($passphrase !== null && $passphrase !== '') {
                // Pass the passphrase via a 0600 file inside the ephemeral
                // GNUPGHOME (shredded with the homedir in the finally) rather than
                // on argv, where it would be world-readable in /proc/<pid>/cmdline.
                $passFile = $home.'/passphrase';
                file_put_contents($passFile, $passphrase);
                @chmod($passFile, 0600);
                $argv[] = '--passphrase-file';
                $argv[] = $passFile;
            }
            $argv[] = '--decrypt';
            $argv[] = $msgFile;

            return BinaryProcess::run($argv, self::TIMEOUT);
        });

        return is_string($result) ? $result : null;
    }

    /**
     * Import metadata for an armored secret key: fingerprint, short key id, the
     * exported armored PUBLIC key, the primary key's algorithm/length/curve/
     * creation/expiry, and every user id on the key (name/email/comment,
     * parsed from gpg's raw "Name (Comment) <email>" uid string) — everything
     * the key list/detail view shows, computed once here rather than on every
     * read. Best-effort — null when gpg is unavailable or the key is unreadable.
     *
     * @return array{
     *   fingerprint:?string, key_id:?string, public_key:?string,
     *   algorithm:?string, key_length:?int, curve:?string,
     *   created_at:?int, expires_at:?int,
     *   identities: list<array{name:?string, email:?string, comment:?string}>,
     * }|null
     */
    public function importInfo(string $armoredSecretKey): ?array
    {
        if (! $this->available() || $armoredSecretKey === '') {
            return null;
        }

        $result = $this->inHome(function (string $home) use ($armoredSecretKey): ?array {
            $keyFile = $home.'/key.asc';
            file_put_contents($keyFile, $armoredSecretKey);
            BinaryProcess::run($this->base($home, ['--import', $keyFile]), self::TIMEOUT);

            $colons = BinaryProcess::run($this->base($home, ['--with-colons', '--fixed-list-mode', '--list-secret-keys']), self::TIMEOUT);
            if ($colons === null) {
                return null;
            }

            $public = BinaryProcess::run($this->base($home, ['--armor', '--export']), self::TIMEOUT);

            return [
                ...self::parseColons($colons),
                'public_key' => ($public !== null && trim($public) !== '') ? $public : null,
            ];
        });

        if (! is_array($result)) {
            return null;
        }

        $fp = $result['fingerprint'] ?? null;
        $kid = $result['key_id'] ?? null;
        $pub = $result['public_key'] ?? null;
        $algo = $result['algorithm'] ?? null;
        $len = $result['key_length'] ?? null;
        $curve = $result['curve'] ?? null;
        $created = $result['created_at'] ?? null;
        $expires = $result['expires_at'] ?? null;

        // parseColons() (called inside the closure above) already builds this
        // to the exact shape — is_array() only narrows to bare `array` here
        // (the value crossed the `mixed`-returning inHome() boundary), so
        // assert the real shape explicitly instead of losing it to `array`.
        /** @var list<array{name:?string, email:?string, comment:?string}> $identities */
        $identities = is_array($result['identities'] ?? null) ? $result['identities'] : [];

        return [
            'fingerprint' => is_string($fp) ? $fp : null,
            'key_id' => is_string($kid) ? $kid : null,
            'public_key' => is_string($pub) ? $pub : null,
            'algorithm' => is_string($algo) ? $algo : null,
            'key_length' => is_int($len) ? $len : null,
            'curve' => is_string($curve) ? $curve : null,
            'created_at' => is_int($created) ? $created : null,
            'expires_at' => is_int($expires) ? $expires : null,
            'identities' => $identities,
        ];
    }

    /**
     * Parse `gpg --with-colons --list-secret-keys` (or `--list-keys`) output for
     * the PRIMARY key's own record (first `sec`/`pub` line, ignoring subkeys)
     * plus every `uid` line. Field layout per GnuPG's doc/DETAILS ("Field N" is
     * 1-indexed there, so `$f[N-1]` below): 3=key length, 4=algorithm id,
     * 5=key id, 6=creation date (epoch), 7=expiration date (epoch), 10=user id
     * string, 17=curve name.
     *
     * @return array{
     *   fingerprint:?string, key_id:?string,
     *   algorithm:?string, key_length:?int, curve:?string,
     *   created_at:?int, expires_at:?int,
     *   identities: list<array{name:?string, email:?string, comment:?string}>,
     * }
     */
    private static function parseColons(string $colons): array
    {
        $fingerprint = null;
        $keyId = null;
        $algorithm = null;
        $keyLength = null;
        $curve = null;
        $createdAt = null;
        $expiresAt = null;
        $identities = [];
        $sawPrimary = false;

        foreach (explode("\n", $colons) as $line) {
            $f = explode(':', $line);
            $type = $f[0] ?? '';

            if ($type === 'fpr' && $fingerprint === null && ($f[9] ?? '') !== '') {
                $fingerprint = $f[9];
            }
            if (($type === 'sec' || $type === 'pub') && ! $sawPrimary) {
                $sawPrimary = true;
                $keyIdRaw = $f[4] ?? '';
                $keyId = $keyIdRaw !== '' ? $keyIdRaw : null;
                $keyLengthRaw = $f[2] ?? '';
                $keyLength = ctype_digit($keyLengthRaw) ? (int) $keyLengthRaw : null;
                $algoRaw = $f[3] ?? '';
                $algorithm = self::algorithmName($algoRaw !== '' ? $algoRaw : null);
                $curveRaw = $f[16] ?? '';
                $curve = $curveRaw !== '' ? $curveRaw : null;
                $createdAtRaw = $f[5] ?? '';
                $createdAt = ctype_digit($createdAtRaw) ? (int) $createdAtRaw : null;
                $expiresAtRaw = $f[6] ?? '';
                $expiresAt = ctype_digit($expiresAtRaw) ? (int) $expiresAtRaw : null;
            }
            if ($type === 'uid' && ($f[9] ?? '') !== '') {
                $identities[] = self::parseUserId($f[9]);
            }
        }

        return [
            'fingerprint' => $fingerprint,
            'key_id' => $keyId,
            'algorithm' => $algorithm,
            'key_length' => $keyLength,
            'curve' => $curve,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
            'identities' => $identities,
        ];
    }

    /** OpenPGP public-key algorithm id (RFC 4880 §9.1) to a display name. */
    private static function algorithmName(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            '1', '2', '3' => 'RSA',
            '16' => 'Elgamal',
            '17' => 'DSA',
            '18' => 'ECDH',
            '19' => 'ECDSA',
            '22' => 'EdDSA',
            default => 'PGP-'.$code,
        };
    }

    /**
     * Split a raw OpenPGP user id ("Full Name (Comment) <email@example.com>",
     * any part optional) into its components.
     *
     * @return array{name:?string, email:?string, comment:?string}
     */
    private static function parseUserId(string $raw): array
    {
        $raw = trim($raw);
        $email = null;
        if (preg_match('/<([^>]*)>/', $raw, $m) === 1) {
            $email = trim($m[1]) !== '' ? trim($m[1]) : null;
            $raw = trim(str_replace($m[0], '', $raw));
        }
        $comment = null;
        if (preg_match('/\(([^)]*)\)/', $raw, $m) === 1) {
            $comment = trim($m[1]) !== '' ? trim($m[1]) : null;
            $raw = trim(str_replace($m[0], '', $raw));
        }

        return ['name' => $raw !== '' ? $raw : null, 'email' => $email, 'comment' => $comment];
    }

    /**
     * Base gpg argv for an ephemeral homedir: batch, no tty, quiet, trust every
     * imported key (we only ever import the user's own key into a throwaway home).
     *
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function base(string $home, array $extra): array
    {
        return ['gpg', '--homedir', $home, '--batch', '--no-tty', '--quiet', '--trust-model', 'always', ...$extra];
    }

    /**
     * Run $fn with a fresh 0700 GNUPGHOME temp dir, guaranteeing the dir (and any
     * key material in it) is recursively removed afterwards.
     *
     * @param  callable(string): mixed  $fn
     */
    private function inHome(callable $fn): mixed
    {
        $home = sys_get_temp_dir().'/ll-gpg-'.bin2hex(random_bytes(8));
        @mkdir($home, 0700, true);
        try {
            return $fn($home);
        } catch (Throwable) {
            return null;
        } finally {
            $this->rmrf($home);
        }
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->rmrf($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
