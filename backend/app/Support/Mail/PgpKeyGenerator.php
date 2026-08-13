<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\BinaryProcess;
use Throwable;

/**
 * Server-side OpenPGP key generation via the `gpg` binary, run through
 * BinaryProcess (array-argv, no shell — no injection). Generation runs in an
 * EPHEMERAL, throwaway GNUPGHOME (0700 temp dir) that is recursively shredded in
 * a finally — no private key material ever persists in a keyring. Mirrors
 * PgpDecryptor's ephemeral-home / RAII posture.
 *
 * Supported option set:
 *   - algorithm rsa  → RSA primary (sign+certify) + RSA encryption subkey,
 *                      key_length ∈ {2048, 3072, 4096}
 *   - algorithm ecc  → curve-driven:
 *                        ed25519 (default) → EdDSA primary + ECDH cv25519 subkey
 *                        nistp256/384/521, brainpoolP256r1/384r1/512r1 →
 *                        ECDSA primary + ECDH subkey on the same curve
 *   - one or more identities (Name-Real / Name-Comment / Name-Email); the first
 *     is the primary UID, the rest are added with --quick-add-uid
 *   - expiry: gpg token "0" (never) | "<n>y" | "YYYY-MM-DD"
 *   - optional passphrase (protects the secret key; empty = %no-protection)
 *   - optional separate signing subkey
 */
final class PgpKeyGenerator
{
    private const TIMEOUT = 120;

    /** RSA key lengths we allow (bits). */
    public const RSA_LENGTHS = [2048, 3072, 4096];

    /** ECC curves we offer (ed25519 is the modern default). */
    public const CURVES = ['ed25519', 'nistp256', 'nistp384', 'nistp521', 'brainpoolP256r1', 'brainpoolP384r1', 'brainpoolP512r1'];

    public function available(): bool
    {
        return BinaryProcess::available('gpg');
    }

    /**
     * Generate a keypair. Returns the exported armored public + secret keys plus
     * the parsed fingerprint / key id, or null on any failure (missing binary,
     * bad parameters). The caller persists the result under MailPgpKey.
     *
     * @param  array{
     *     algorithm: string,
     *     key_length?: int,
     *     curve?: string,
     *     identities: list<array{name?:?string, email:string, comment?:?string}>,
     *     expire?: string,
     *     passphrase?: ?string,
     *     signing_subkey?: bool,
     * }  $opts
     * @return array{fingerprint:?string, key_id:?string, public_key:string, private_key:string}|null
     */
    public function generate(array $opts): ?array
    {
        if (! $this->available() || $opts['identities'] === []) {
            return null;
        }

        $result = $this->inHome(function (string $home) use ($opts): ?array {
            $passRaw = $opts['passphrase'] ?? '';
            $passphrase = $passRaw !== '' ? (string) $passRaw : null;
            $expireRaw = $opts['expire'] ?? '0';
            $expire = $expireRaw !== '' ? (string) $expireRaw : '0';

            $params = $this->paramFile($opts, $expire, $passphrase);
            $paramFile = $home.'/params';
            file_put_contents($paramFile, $params);

            $gen = BinaryProcess::runCapture($this->base($home, ['--gen-key', $paramFile]), self::TIMEOUT);
            if (! $gen['ok']) {
                return null;
            }

            $fingerprint = $this->primaryFingerprint($home);
            if ($fingerprint === null) {
                return null;
            }

            // Passphrase file (0600, in the shredded ephemeral home) for the
            // quick-* commands below — never on argv (/proc/<pid>/cmdline leak).
            $passArgs = [];
            if ($passphrase !== null) {
                $passFile = $home.'/passphrase';
                file_put_contents($passFile, $passphrase);
                @chmod($passFile, 0600);
                $passArgs = ['--passphrase-file', $passFile];
            }

            // Additional UIDs (the first is the primary from the param file).
            foreach (array_slice($opts['identities'], 1) as $identity) {
                $uid = $this->uidString($identity);
                if ($uid === '') {
                    continue;
                }
                BinaryProcess::run($this->base($home, [...$passArgs, '--quick-add-uid', $fingerprint, $uid]), self::TIMEOUT);
            }

            // Optional dedicated signing subkey.
            if (($opts['signing_subkey'] ?? false) === true) {
                $subAlgo = $this->subkeyAlgo($opts);
                BinaryProcess::run($this->base($home, [...$passArgs, '--quick-add-key', $fingerprint, $subAlgo, 'sign', $expire]), self::TIMEOUT);
            }

            $public = BinaryProcess::run($this->base($home, ['--armor', '--export', $fingerprint]), self::TIMEOUT);
            $secret = BinaryProcess::run($this->base($home, [...$passArgs, '--armor', '--export-secret-keys', $fingerprint]), self::TIMEOUT);

            if (! is_string($public) || trim($public) === '' || ! is_string($secret) || trim($secret) === '') {
                return null;
            }

            return [
                'fingerprint' => $fingerprint,
                'key_id' => substr($fingerprint, -16) ?: null,
                'public_key' => $public,
                'private_key' => $secret,
            ];
        });

        if (! is_array($result)) {
            return null;
        }

        $fp = $result['fingerprint'] ?? null;
        $kid = $result['key_id'] ?? null;
        $pub = $result['public_key'] ?? null;
        $sec = $result['private_key'] ?? null;
        if (! is_string($pub) || ! is_string($sec)) {
            return null;
        }

        return [
            'fingerprint' => is_string($fp) ? $fp : null,
            'key_id' => is_string($kid) ? $kid : null,
            'public_key' => $pub,
            'private_key' => $sec,
        ];
    }

    /**
     * Build the gpg batch key-parameter file for the primary key + encryption
     * subkey (the standard layout: sign/certify primary, encrypt subkey).
     *
     * @param  array{algorithm:string, key_length?:int, curve?:string, identities:list<array{name?:?string, email:string, comment?:?string}>, expire?:string, passphrase?:?string, signing_subkey?:bool}  $opts
     */
    private function paramFile(array $opts, string $expire, ?string $passphrase): string
    {
        // gpg requires the parameter block to START with Key-Type.
        $lines = ['%echo generating'];

        if ($opts['algorithm'] === 'rsa') {
            $len = (string) ($opts['key_length'] ?? 3072);
            $lines[] = 'Key-Type: RSA';
            $lines[] = 'Key-Length: '.$len;
            $lines[] = 'Key-Usage: sign';
            $lines[] = 'Subkey-Type: RSA';
            $lines[] = 'Subkey-Length: '.$len;
            $lines[] = 'Subkey-Usage: encrypt';
        } else {
            $curve = $opts['curve'] ?? 'ed25519';
            if ($curve === 'ed25519') {
                $lines[] = 'Key-Type: EDDSA';
                $lines[] = 'Key-Curve: ed25519';
                $lines[] = 'Key-Usage: sign';
                $lines[] = 'Subkey-Type: ECDH';
                $lines[] = 'Subkey-Curve: cv25519';
            } else {
                $lines[] = 'Key-Type: ECDSA';
                $lines[] = 'Key-Curve: '.$curve;
                $lines[] = 'Key-Usage: sign';
                $lines[] = 'Subkey-Type: ECDH';
                $lines[] = 'Subkey-Curve: '.$curve;
            }
            $lines[] = 'Subkey-Usage: encrypt';
        }

        $primary = $opts['identities'][0];
        if (($primary['name'] ?? null) !== null && $primary['name'] !== '') {
            $lines[] = 'Name-Real: '.$this->clean($primary['name']);
        }
        if (($primary['comment'] ?? null) !== null && $primary['comment'] !== '') {
            $lines[] = 'Name-Comment: '.$this->clean($primary['comment']);
        }
        $lines[] = 'Name-Email: '.$this->clean($primary['email']);

        $lines[] = 'Expire-Date: '.$expire;

        if ($passphrase === null) {
            $lines[] = '%no-protection';
        } else {
            $lines[] = 'Passphrase: '.$this->clean($passphrase);
        }

        $lines[] = '%commit';
        $lines[] = '%echo done';

        return implode("\n", $lines)."\n";
    }

    /** Strip control/newline characters so a value can't break the param-file grammar. */
    private function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/', ' ', $value));
    }

    /**
     * gpg quick-add-key algorithm token for a signing subkey mirroring the
     * primary's algorithm/strength.
     *
     * @param  array{algorithm:string, key_length?:int, curve?:string}  $opts
     */
    private function subkeyAlgo(array $opts): string
    {
        if ($opts['algorithm'] === 'rsa') {
            return 'rsa'.(string) ($opts['key_length'] ?? 3072);
        }

        return (string) ($opts['curve'] ?? 'ed25519');
    }

    /**
     * Compose a gpg UID string: "Name (Comment) <email>" (or a subset).
     *
     * @param  array{name?:?string, email?:string, comment?:?string}  $identity
     */
    private function uidString(array $identity): string
    {
        $email = $this->clean($identity['email'] ?? '');
        if ($email === '') {
            return '';
        }
        $name = $this->clean($identity['name'] ?? '');
        $comment = $this->clean($identity['comment'] ?? '');

        $uid = $name;
        if ($comment !== '') {
            $uid = trim($uid.' ('.$comment.')');
        }

        return trim($uid.' <'.$email.'>');
    }

    /** First fingerprint from the secret keyring in the ephemeral home. */
    private function primaryFingerprint(string $home): ?string
    {
        $colons = BinaryProcess::run($this->base($home, ['--with-colons', '--list-secret-keys']), self::TIMEOUT);
        if ($colons === null) {
            return null;
        }
        foreach (explode("\n", $colons) as $line) {
            $f = explode(':', $line);
            if (($f[0] ?? '') === 'fpr' && ($f[9] ?? '') !== '') {
                return $f[9];
            }
        }

        return null;
    }

    /**
     * Base gpg argv for an ephemeral homedir (batch, no tty, quiet, loopback
     * pinentry so no agent prompt).
     *
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function base(string $home, array $extra): array
    {
        return ['gpg', '--homedir', $home, '--batch', '--no-tty', '--quiet', '--pinentry-mode', 'loopback', ...$extra];
    }

    /**
     * Run $fn with a fresh 0700 GNUPGHOME temp dir, guaranteeing it (and any key
     * material in it) is recursively removed afterwards.
     *
     * @param  callable(string): mixed  $fn
     */
    private function inHome(callable $fn): mixed
    {
        $home = sys_get_temp_dir().'/ll-gpggen-'.bin2hex(random_bytes(8));
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
