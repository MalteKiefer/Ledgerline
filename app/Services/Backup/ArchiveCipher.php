<?php

declare(strict_types=1);

namespace App\Services\Backup;

use RuntimeException;

/**
 * Streaming symmetric encryption for backup archives.
 *
 * A passphrase is stretched to a key with libsodium's Argon2id (crypto_pwhash),
 * then the file is encrypted chunk-by-chunk with an authenticated secretstream
 * (XChaCha20-Poly1305) so arbitrarily large archives never sit fully in memory.
 * The output is: magic | salt | stream-header | framed(ciphertext-chunks).
 *
 * This protects the archive at rest on the remote destination so a backup
 * passphrase alone can decrypt a downloaded archive with `backups:decrypt`.
 */
final class ArchiveCipher
{
    /** Legacy format: MODERATE KDF params, not stored in the header. */
    private const MAGIC_V1 = "LLBK1\0";

    /** Current format: KDF opslimit + memlimit are stored so they can be raised
     *  without breaking older archives. */
    private const MAGIC_V2 = "LLBK2\0";

    private const CHUNK = 65536; // plaintext bytes per secretstream chunk

    public function encryptFile(string $inPath, string $outPath, string $passphrase): void
    {
        $in = $this->open($inPath, 'rb');
        $out = $this->open($outPath, 'wb');
        try {
            // Argon2id at SENSITIVE cost — this passphrase protects the DB dump +
            // wrapped vault-key material at rest on untrusted remote storage, so it
            // is an offline-cracking target and warrants the strongest KDF preset.
            $ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE;
            $mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE;
            $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $key = $this->deriveKey($passphrase, $salt, $ops, $mem);
            [$state, $header] = $this->initPush($key);

            // Header: magic | opslimit(u32) | memlimit(u32) | salt | stream-header.
            $this->write($out, self::MAGIC_V2);
            $this->write($out, pack('N', $ops));
            $this->write($out, pack('N', $mem));
            $this->write($out, $salt);
            $this->write($out, $header);

            while (! feof($in)) {
                $chunk = fread($in, self::CHUNK);
                if ($chunk === false) {
                    throw new RuntimeException('Read error while encrypting.');
                }
                $tag = feof($in)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                $this->write($out, pack('N', strlen($cipher)));
                $this->write($out, $cipher);
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decryptFile(string $inPath, string $outPath, string $passphrase): void
    {
        $in = $this->open($inPath, 'rb');
        $out = $this->open($outPath, 'wb');
        try {
            $magic = fread($in, strlen(self::MAGIC_V2));
            if ($magic === self::MAGIC_V2) {
                // KDF params are stored in the header (raisable without a break).
                $ops = $this->readU32($in);
                $mem = $this->readU32($in);
            } elseif ($magic === self::MAGIC_V1) {
                // Legacy archives derived with the MODERATE preset.
                $ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
                $mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;
            } else {
                throw new RuntimeException('Not a Ledgerline backup archive.');
            }
            $salt = $this->readExactly($in, SODIUM_CRYPTO_PWHASH_SALTBYTES, 'salt');
            $header = $this->readExactly($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES, 'stream header');
            $key = $this->deriveKey($passphrase, $salt, $ops, $mem);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);

            $sawFinal = false;
            while (! feof($in)) {
                $lenRaw = fread($in, 4);
                if ($lenRaw === '' || $lenRaw === false) {
                    break;
                }
                if (strlen($lenRaw) !== 4) {
                    throw new RuntimeException('Truncated archive (chunk length).');
                }
                $unpacked = unpack('N', $lenRaw);
                if ($unpacked === false) {
                    throw new RuntimeException('Corrupt archive (chunk length).');
                }
                $len = $unpacked[1];
                $cipher = fread($in, $len);
                if ($cipher === false || strlen($cipher) !== $len) {
                    throw new RuntimeException('Truncated archive (chunk body).');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('Wrong passphrase or corrupted archive.');
                }
                [$plain, $tag] = $result;
                $this->write($out, $plain);
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $sawFinal = true;
                    break;
                }
            }
            // The stream must end with the FINAL tag; otherwise it was truncated
            // and the surviving prefix must not be accepted as a complete restore.
            if (! $sawFinal) {
                throw new RuntimeException('Archive is incomplete (missing final marker).');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private function deriveKey(string $passphrase, string $salt, int $opslimit, int $memlimit): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $passphrase,
            $salt,
            $opslimit,
            $memlimit,
        );
    }

    /** @return array{0: string, 1: string} [state, header] */
    private function initPush(string $key): array
    {
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

        return [$state[0], $state[1]];
    }

    /**
     * Write exactly $data or throw: a short/failed write would silently
     * truncate the archive, only to be discovered as corruption at restore.
     *
     * @param  resource  $out
     */
    private function write($out, string $data): void
    {
        $expected = strlen($data);
        if ($expected === 0) {
            return;
        }
        $written = fwrite($out, $data);
        if ($written === false || $written !== $expected) {
            throw new RuntimeException('Short write while encrypting/decrypting the archive.');
        }
    }

    /**
     * Read exactly $length bytes or throw: a short read means the archive was
     * truncated/corrupt, which must fail loudly before any crypto touches it.
     *
     * @param  resource  $in
     */
    private function readExactly($in, int $length, string $what): string
    {
        if ($length < 1) {
            throw new RuntimeException("Invalid read length for {$what}.");
        }
        $data = fread($in, $length);
        if ($data === false || strlen($data) !== $length) {
            throw new RuntimeException("Truncated archive ({$what}).");
        }

        return $data;
    }

    /**
     * Read a big-endian u32 or throw on a truncated/corrupt header.
     *
     * @param  resource  $in
     */
    private function readU32($in): int
    {
        $raw = $this->readExactly($in, 4, 'KDF parameter');
        $unpacked = unpack('N', $raw);
        if ($unpacked === false) {
            throw new RuntimeException('Corrupt archive (KDF parameter).');
        }

        return $unpacked[1];
    }

    /** @return resource */
    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        return $handle;
    }
}
