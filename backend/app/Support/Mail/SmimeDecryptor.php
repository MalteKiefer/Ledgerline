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
     * message, or null on any failure. When the private key PEM is
     * passphrase-protected (e.g. a server-generated key), pass $passphrase so
     * openssl can unlock it; null (the default) leaves existing callers with an
     * unencrypted key unchanged.
     */
    public function decrypt(string $keyPem, string $certPem, string $smimeMessage, ?string $passphrase = null): ?string
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
        if ($passphrase !== null && $passphrase !== '') {
            // file: (RAII temp) not pass:<value> — no /proc/<pid>/cmdline leak.
            $passFile = DiskTempFile::create('smime-inpass');
            file_put_contents($passFile->path(), $passphrase);
            $argv[] = '-passin';
            $argv[] = 'file:'.$passFile->path();
        }
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

    /**
     * Parse a certificate PEM's identity + validity + key metadata — everything
     * the key detail view shows for an S/MIME key. Non-secret (the cert is the
     * public half); best-effort, null when openssl is unavailable or the cert
     * is unreadable.
     *
     * @return array{
     *   subject:?string, issuer:?string, serial:?string,
     *   not_before:?int, not_after:?int, sha256_fingerprint:?string,
     *   algorithm:?string, key_length:?int, curve:?string,
     *   name:?string, email:?string,
     * }|null
     */
    public function certInfo(string $certPem): ?array
    {
        if (! $this->available() || trim($certPem) === '') {
            return null;
        }

        $in = DiskTempFile::create('smime-cert-info');
        file_put_contents($in->path(), $certPem);

        $out = BinaryProcess::run([
            'openssl', 'x509', '-in', $in->path(), '-noout',
            '-subject', '-issuer', '-serial', '-startdate', '-enddate', '-fingerprint', '-sha256', '-text',
        ], self::TIMEOUT);
        if (! is_string($out) || trim($out) === '') {
            return null;
        }

        $subject = self::grabLine($out, 'subject=');
        $issuer = self::grabLine($out, 'issuer=');
        $serial = self::grabLine($out, 'serial=');
        $notBefore = self::grabLine($out, 'notBefore=');
        $notAfter = self::grabLine($out, 'notAfter=');

        $sha256 = null;
        if (preg_match('/SHA256 Fingerprint=([0-9A-Fa-f:]+)/', $out, $m) === 1) {
            $sha256 = str_replace(':', '', strtoupper($m[1]));
        }

        $algorithm = null;
        $keyLength = null;
        $curve = null;
        if (preg_match('/Public Key Algorithm:\s*(\S+)/', $out, $m) === 1) {
            $algorithm = self::certAlgorithmName($m[1]);
        }
        if (preg_match('/Public-Key:\s*\((\d+)\s*bit\)/', $out, $m) === 1) {
            $keyLength = (int) $m[1];
        }
        if (preg_match('/NIST CURVE:\s*(\S+)/', $out, $m) === 1) {
            $curve = $m[1];
        } elseif (preg_match('/ASN1 OID:\s*(\S+)/', $out, $m) === 1) {
            $curve = $m[1];
        }

        return [
            'subject' => $subject,
            'issuer' => $issuer,
            'serial' => $serial,
            'not_before' => $notBefore !== null ? (strtotime($notBefore) ?: null) : null,
            'not_after' => $notAfter !== null ? (strtotime($notAfter) ?: null) : null,
            'sha256_fingerprint' => $sha256,
            'algorithm' => $algorithm,
            'key_length' => $keyLength,
            'curve' => $curve,
            'name' => self::extractDnAttr($subject, 'CN'),
            'email' => self::extractDnAttr($subject, 'emailAddress') ?? self::extractSanEmail($out),
        ];
    }

    /** First line of openssl's `-noout -subject`-style output starting with $prefix. */
    private static function grabLine(string $out, string $prefix): ?string
    {
        foreach (explode("\n", $out) as $line) {
            if (str_starts_with($line, $prefix)) {
                $v = trim(substr($line, strlen($prefix)));

                return $v !== '' ? $v : null;
            }
        }

        return null;
    }

    /**
     * Pull one RDN attribute (e.g. "CN"/"emailAddress") out of an OpenSSL
     * subject/issuer DN string — tolerant of both the older "/CN=x/O=y" and the
     * newer "CN = x, O = y" formatting OpenSSL versions emit.
     */
    private static function extractDnAttr(?string $dn, string $attr): ?string
    {
        if ($dn === null) {
            return null;
        }
        if (preg_match('/(?:^|[,\/])\s*'.preg_quote($attr, '/').'\s*=\s*([^,\/]+)/i', $dn, $m) === 1) {
            $v = trim($m[1]);

            return $v !== '' ? $v : null;
        }

        return null;
    }

    /** The first rfc822Name (email) in the cert's Subject Alternative Name extension, if any. */
    private static function extractSanEmail(string $certText): ?string
    {
        if (preg_match('/Subject Alternative Name:\s*\n\s*(.+)/', $certText, $m) === 1
            && preg_match('/email:([^,\s]+)/i', $m[1], $em) === 1) {
            return $em[1];
        }

        return null;
    }

    /** Certificate public-key OID/algorithm name (as openssl prints it) to a short display name. */
    private static function certAlgorithmName(string $oidName): string
    {
        $lower = strtolower($oidName);

        return match (true) {
            str_contains($lower, 'rsa') => 'RSA',
            str_contains($lower, 'ecpublickey') => 'EC',
            str_contains($lower, 'dsa') => 'DSA',
            str_contains($lower, 'ed25519'), str_contains($lower, 'ed448') => 'EdDSA',
            default => $oidName,
        };
    }
}
