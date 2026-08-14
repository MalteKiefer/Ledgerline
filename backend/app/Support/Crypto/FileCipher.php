<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use App\Support\BinaryProcess;
use Throwable;

/**
 * Server-side file encryption/decryption for the Files module — OpenPGP via `gpg`
 * and S/MIME via `openssl` (both in the runtime image), array-argv through
 * BinaryProcess (no shell → no injection).
 *
 * Encryption is public-key (asymmetric): the plaintext is encrypted to one or more
 * recipient public keys/certs — always including the user's OWN key so they can
 * decrypt it again. Decryption uses the user's own secret key + passphrase. Every
 * gpg call runs in an EPHEMERAL 0700 GNUPGHOME that is shredded in a finally, so no
 * key material persists in a keyring; openssl reads keys from 0600 temp files that
 * are unlinked too. Plaintext is transient (same posture as the mail decryptors).
 */
final class FileCipher
{
    private const TIMEOUT = 120;

    public function pgpAvailable(): bool
    {
        return BinaryProcess::available('gpg');
    }

    public function smimeAvailable(): bool
    {
        return BinaryProcess::available('openssl');
    }

    /**
     * Encrypt $inPath to the given armored public keys → $outPath (binary OpenPGP).
     *
     * @param  list<string>  $recipientPublicKeys  armored public keys (own + others)
     */
    public function encryptPgp(string $inPath, array $recipientPublicKeys, string $outPath): bool
    {
        if (! $this->pgpAvailable() || $recipientPublicKeys === []) {
            return false;
        }

        return (bool) $this->inHome(function (string $home) use ($inPath, $recipientPublicKeys, $outPath): bool {
            $fprs = [];
            foreach ($recipientPublicKeys as $i => $armored) {
                if (! is_string($armored) || trim($armored) === '') {
                    continue;
                }
                $kf = $home.'/pub-'.$i.'.asc';
                file_put_contents($kf, $armored);
                BinaryProcess::run($this->base($home, ['--import', $kf]), self::TIMEOUT);
            }
            // Collect every imported public key's primary fingerprint as a recipient.
            $colons = BinaryProcess::run($this->base($home, ['--with-colons', '--list-keys']), self::TIMEOUT);
            foreach (explode("\n", (string) $colons) as $line) {
                $f = explode(':', $line);
                if (($f[0] ?? '') === 'fpr' && ($f[9] ?? '') !== '') {
                    $fprs[] = $f[9];
                }
            }
            $fprs = array_values(array_unique($fprs));
            if ($fprs === []) {
                return false;
            }
            $argv = $this->base($home, []);
            foreach ($fprs as $fpr) {
                $argv[] = '--recipient';
                $argv[] = $fpr;
            }
            $argv[] = '--output';
            $argv[] = $outPath;
            $argv[] = '--encrypt';
            $argv[] = $inPath;
            BinaryProcess::run($argv, self::TIMEOUT);

            return is_file($outPath) && filesize($outPath) > 0;
        });
    }

    /** Decrypt $inPath with the armored secret key → $outPath. */
    public function decryptPgp(string $inPath, string $armoredSecretKey, ?string $passphrase, string $outPath): bool
    {
        if (! $this->pgpAvailable() || trim($armoredSecretKey) === '') {
            return false;
        }

        return (bool) $this->inHome(function (string $home) use ($inPath, $armoredSecretKey, $passphrase, $outPath): bool {
            $kf = $home.'/sec.asc';
            file_put_contents($kf, $armoredSecretKey);
            BinaryProcess::run($this->base($home, ['--import', $kf]), self::TIMEOUT);

            $argv = $this->base($home, ['--pinentry-mode', 'loopback']);
            if ($passphrase !== null && $passphrase !== '') {
                $pf = $home.'/pass';
                file_put_contents($pf, $passphrase);
                @chmod($pf, 0600);
                $argv[] = '--passphrase-file';
                $argv[] = $pf;
            }
            $argv[] = '--output';
            $argv[] = $outPath;
            $argv[] = '--decrypt';
            $argv[] = $inPath;
            BinaryProcess::run($argv, self::TIMEOUT);

            return is_file($outPath) && filesize($outPath) > 0;
        });
    }

    /**
     * S/MIME-encrypt $inPath to the given PEM recipient certs → $outPath (DER PKCS#7).
     *
     * @param  list<string>  $recipientCertsPem
     */
    public function encryptSmime(string $inPath, array $recipientCertsPem, string $outPath): bool
    {
        if (! $this->smimeAvailable() || $recipientCertsPem === []) {
            return false;
        }
        $tmpDir = sys_get_temp_dir().'/ll-smime-'.bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0700, true);
        try {
            $certFiles = [];
            foreach ($recipientCertsPem as $i => $pem) {
                if (! is_string($pem) || trim($pem) === '') {
                    continue;
                }
                $cf = $tmpDir.'/cert-'.$i.'.pem';
                file_put_contents($cf, $pem);
                $certFiles[] = $cf;
            }
            if ($certFiles === []) {
                return false;
            }
            $argv = ['openssl', 'smime', '-encrypt', '-binary', '-aes-256-cbc', '-in', $inPath, '-out', $outPath, '-outform', 'DER', ...$certFiles];
            BinaryProcess::run($argv, self::TIMEOUT);

            return is_file($outPath) && filesize($outPath) > 0;
        } finally {
            $this->rmrf($tmpDir);
        }
    }

    /** S/MIME-decrypt $inPath (DER) with the PEM private key + cert → $outPath. */
    public function decryptSmime(string $inPath, string $keyPem, string $certPem, string $outPath): bool
    {
        if (! $this->smimeAvailable() || trim($keyPem) === '') {
            return false;
        }
        $tmpDir = sys_get_temp_dir().'/ll-smime-'.bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0700, true);
        try {
            $kf = $tmpDir.'/key.pem';
            file_put_contents($kf, $keyPem);
            @chmod($kf, 0600);
            $argv = ['openssl', 'smime', '-decrypt', '-binary', '-inform', 'DER', '-in', $inPath, '-out', $outPath, '-inkey', $kf];
            if (trim($certPem) !== '') {
                $cf = $tmpDir.'/cert.pem';
                file_put_contents($cf, $certPem);
                $argv[] = '-recip';
                $argv[] = $cf;
            }
            BinaryProcess::run($argv, self::TIMEOUT);

            return is_file($outPath) && filesize($outPath) > 0;
        } finally {
            $this->rmrf($tmpDir);
        }
    }

    /**
     * Fingerprint of an armored PGP public key (for storing an imported recipient),
     * or null. Best-effort.
     */
    public function pgpFingerprint(string $armoredPublicKey): ?string
    {
        if (! $this->pgpAvailable() || trim($armoredPublicKey) === '') {
            return null;
        }
        $r = $this->inHome(function (string $home) use ($armoredPublicKey): ?string {
            $kf = $home.'/pub.asc';
            file_put_contents($kf, $armoredPublicKey);
            BinaryProcess::run($this->base($home, ['--import', $kf]), self::TIMEOUT);
            $colons = BinaryProcess::run($this->base($home, ['--with-colons', '--list-keys']), self::TIMEOUT);
            foreach (explode("\n", (string) $colons) as $line) {
                $f = explode(':', $line);
                if (($f[0] ?? '') === 'fpr' && ($f[9] ?? '') !== '') {
                    return $f[9];
                }
            }

            return null;
        });

        return is_string($r) ? $r : null;
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function base(string $home, array $extra): array
    {
        // --yes so gpg overwrites the pre-created (empty) --output temp file instead
        // of prompting "File exists. Overwrite?" and failing in batch mode.
        return ['gpg', '--homedir', $home, '--batch', '--yes', '--no-tty', '--quiet', '--trust-model', 'always', ...$extra];
    }

    /** @param  callable(string): mixed  $fn */
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
