<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\BinaryProcess;
use App\Support\DiskTempFile;

/**
 * Server-side S/MIME key generation via the `openssl` binary, run through
 * BinaryProcess (array-argv, no shell). Produces a self-signed X.509
 * certificate + RSA private key (PEM). All key material lives only in RAII temp
 * files (DiskTempFile) unlinked when they go out of scope.
 *
 * If a passphrase is supplied the private key PEM is encrypted with it (openssl
 * `-passout`) and the passphrase is stored under the app's `encrypted` cast so
 * decryption can supply it; otherwise the key is emitted unencrypted (`-nodes`),
 * mirroring the PKCS#12 import path.
 */
final class SmimeKeyGenerator
{
    private const TIMEOUT = 120;

    /** RSA key lengths we allow (bits). */
    public const RSA_LENGTHS = [2048, 3072, 4096];

    public function available(): bool
    {
        return BinaryProcess::available('openssl');
    }

    /**
     * Generate a self-signed S/MIME cert + key. Returns the PEM key + cert (and
     * whether the key is passphrase-protected), or null on any failure.
     *
     * @param  array{name?:?string, email:string, key_length?:int, days?:int, passphrase?:?string}  $opts
     * @return array{key:string, cert:string, protected:bool}|null
     */
    public function generate(array $opts): ?array
    {
        if (! $this->available() || ($opts['email'] ?? '') === '') {
            return null;
        }

        $lenRaw = $opts['key_length'] ?? 3072;
        $length = in_array($lenRaw, self::RSA_LENGTHS, true) ? (int) $lenRaw : 3072;
        $days = (int) ($opts['days'] ?? 730);
        if ($days < 1 || $days > 3650) {
            $days = 730;
        }
        $passphrase = ($opts['passphrase'] ?? '') !== '' ? (string) $opts['passphrase'] : null;
        $subj = $this->subject($opts);

        $key = DiskTempFile::create('smime-gen-key');
        $cert = DiskTempFile::create('smime-gen-cert');

        $argv = ['openssl', 'req', '-x509', '-newkey', 'rsa:'.$length,
            '-keyout', $key->path(), '-out', $cert->path(),
            '-days', (string) $days, '-subj', $subj, '-addext', 'subjectAltName=email:'.$this->clean($opts['email'])];

        if ($passphrase === null) {
            $argv[] = '-nodes';
        } else {
            // Read the passphrase from a RAII temp file (`file:`) rather than
            // pass:<value> on argv, where it would leak via /proc/<pid>/cmdline.
            $passFile = DiskTempFile::create('smime-gen-pass');
            file_put_contents($passFile->path(), $passphrase);
            $argv[] = '-passout';
            $argv[] = 'file:'.$passFile->path();
        }

        $gen = BinaryProcess::runCapture($argv, self::TIMEOUT);
        if (! $gen['ok']) {
            return null;
        }

        $keyPem = trim((string) file_get_contents($key->path()));
        $certPem = trim((string) file_get_contents($cert->path()));
        if ($keyPem === '' || $certPem === '' || ! str_contains($certPem, 'BEGIN CERTIFICATE')) {
            return null;
        }

        return ['key' => $keyPem, 'cert' => $certPem, 'protected' => $passphrase !== null];
    }

    /**
     * Build an OpenSSL subject DN. Slashes/control chars are stripped so a value
     * can't inject extra DN components.
     *
     * @param  array{name?:?string, email:string}  $opts
     */
    private function subject(array $opts): string
    {
        $cn = ($opts['name'] ?? null) !== null && $opts['name'] !== '' ? $this->clean((string) $opts['name']) : $this->clean($opts['email']);

        return '/CN='.$cn.'/emailAddress='.$this->clean($opts['email']);
    }

    private function clean(string $value): string
    {
        return trim(str_replace(['/', "\n", "\r", "\0"], ' ', $value));
    }
}
