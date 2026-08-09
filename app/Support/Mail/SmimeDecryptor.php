<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\BinaryProcess;
use App\Support\DiskTempFile;

/**
 * Server-side S/MIME decryption via the `openssl` binary, run through
 * BinaryProcess (array-argv, no shell). Also converts an uploaded PKCS#12
 * (.p12/.pfx) bundle into a private-key PEM + certificate PEM at import time.
 * All key material lives only in RAII temp files (DiskTempFile) that are
 * unlinked when they go out of scope; the decrypted plaintext is transient.
 */
final class SmimeDecryptor
{
    private const TIMEOUT = 30;

    public function available(): bool
    {
        return BinaryProcess::available('openssl');
    }

    /**
     * Decrypt an S/MIME enveloped-data message. Returns the decrypted MIME
     * message, or null on any failure.
     */
    public function decrypt(string $keyPem, string $certPem, string $smimeMessage): ?string
    {
        if (! $this->available() || $keyPem === '' || $smimeMessage === '') {
            return null;
        }

        $key = DiskTempFile::create('smime-key');
        $cert = DiskTempFile::create('smime-cert');
        $msg = DiskTempFile::create('smime-msg');
        file_put_contents($key->path(), $keyPem);
        file_put_contents($cert->path(), $certPem);
        file_put_contents($msg->path(), $smimeMessage);

        $argv = ['openssl', 'smime', '-decrypt', '-inform', 'SMIME', '-in', $msg->path(), '-inkey', $key->path()];
        if (trim($certPem) !== '') {
            $argv[] = '-recip';
            $argv[] = $cert->path();
        }

        return BinaryProcess::run($argv, self::TIMEOUT);
    }

    /**
     * Convert a PKCS#12 bundle into [privateKeyPem, certPem]. Returns null when
     * the bundle can't be read (wrong passphrase / unsupported cipher / no
     * openssl). The private key is emitted unencrypted (`-nodes`) so it can be
     * stored under the app's own `encrypted` cast.
     *
     * @return array{key:string, cert:string}|null
     */
    public function pkcs12ToPem(string $p12, ?string $passphrase): ?array
    {
        if (! $this->available() || $p12 === '') {
            return null;
        }

        $in = DiskTempFile::create('smime-p12');
        file_put_contents($in->path(), $p12);
        // Read the import passphrase from a RAII temp file (`file:`) rather than
        // `pass:<value>` on argv, where it would leak via /proc/<pid>/cmdline.
        $passFile = DiskTempFile::create('smime-pass');
        file_put_contents($passFile->path(), $passphrase ?? '');
        $pass = 'file:'.$passFile->path();

        $key = BinaryProcess::run(['openssl', 'pkcs12', '-in', $in->path(), '-nocerts', '-nodes', '-passin', $pass], self::TIMEOUT);
        $cert = BinaryProcess::run(['openssl', 'pkcs12', '-in', $in->path(), '-clcerts', '-nokeys', '-passin', $pass], self::TIMEOUT);

        if (! is_string($key) || ! is_string($cert)) {
            return null;
        }

        $key = $this->extractPem($key, 'PRIVATE KEY');
        $cert = $this->extractPem($cert, 'CERTIFICATE');
        if ($key === null || $cert === null) {
            return null;
        }

        return ['key' => $key, 'cert' => $cert];
    }

    /** Isolate the first PEM block of a kind from openssl's chatty output. */
    private function extractPem(string $output, string $kind): ?string
    {
        // Match "PRIVATE KEY" and "ENCRYPTED PRIVATE KEY"/"RSA PRIVATE KEY" too.
        $pattern = '/-----BEGIN (?:[A-Z ]*)?'.preg_quote($kind, '/').'-----.*?-----END (?:[A-Z ]*)?'.preg_quote($kind, '/').'-----/s';
        if (preg_match($pattern, $output, $m) === 1) {
            return $m[0];
        }

        return null;
    }
}
