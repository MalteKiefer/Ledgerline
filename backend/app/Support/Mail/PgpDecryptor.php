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
     * Import metadata for an armored secret key: fingerprint, short key id, and
     * the exported armored PUBLIC key. Best-effort — null when gpg is
     * unavailable or the key is unreadable.
     *
     * @return array{fingerprint:?string, key_id:?string, public_key:?string}|null
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

            $colons = BinaryProcess::run($this->base($home, ['--with-colons', '--list-secret-keys']), self::TIMEOUT);
            if ($colons === null) {
                return null;
            }

            $fingerprint = null;
            $keyId = null;
            foreach (explode("\n", $colons) as $line) {
                $f = explode(':', $line);
                if ($f[0] === 'fpr' && $fingerprint === null && ($f[9] ?? '') !== '') {
                    $fingerprint = $f[9];
                }
                if ($f[0] === 'sec' && $keyId === null && ($f[4] ?? '') !== '') {
                    $keyId = $f[4];
                }
            }

            $public = BinaryProcess::run($this->base($home, ['--armor', '--export']), self::TIMEOUT);

            return [
                'fingerprint' => $fingerprint,
                'key_id' => $keyId,
                'public_key' => ($public !== null && trim($public) !== '') ? $public : null,
            ];
        });

        if (! is_array($result)) {
            return null;
        }

        $fp = $result['fingerprint'] ?? null;
        $kid = $result['key_id'] ?? null;
        $pub = $result['public_key'] ?? null;

        return [
            'fingerprint' => is_string($fp) ? $fp : null,
            'key_id' => is_string($kid) ? $kid : null,
            'public_key' => is_string($pub) ? $pub : null,
        ];
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
