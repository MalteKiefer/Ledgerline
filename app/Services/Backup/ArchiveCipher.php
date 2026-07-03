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
 * This protects the archive at rest on the remote destination. It is unrelated
 * to the zero-knowledge vault key (which the server never has); it exists so a
 * backup passphrase alone can decrypt a downloaded archive with `backups:decrypt`.
 */
final class ArchiveCipher
{
    private const MAGIC = "LLBK1\0";

    private const CHUNK = 65536; // plaintext bytes per secretstream chunk

    public function encryptFile(string $inPath, string $outPath, string $passphrase): void
    {
        $in = $this->open($inPath, 'rb');
        $out = $this->open($outPath, 'wb');
        try {
            $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $key = $this->deriveKey($passphrase, $salt);
            [$state, $header] = $this->initPush($key);

            fwrite($out, self::MAGIC);
            fwrite($out, $salt);
            fwrite($out, $header);

            while (! feof($in)) {
                $chunk = fread($in, self::CHUNK);
                if ($chunk === false) {
                    throw new RuntimeException('Read error while encrypting.');
                }
                $tag = feof($in)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($out, pack('N', strlen($cipher)));
                fwrite($out, $cipher);
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
            if (fread($in, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('Not a Ledgerline backup archive.');
            }
            $salt = fread($in, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $key = $this->deriveKey($passphrase, $salt);
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
                $len = unpack('N', $lenRaw)[1];
                $cipher = fread($in, $len);
                if ($cipher === false || strlen($cipher) !== $len) {
                    throw new RuntimeException('Truncated archive (chunk body).');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('Wrong passphrase or corrupted archive.');
                }
                [$plain, $tag] = $result;
                fwrite($out, $plain);
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

    private function deriveKey(string $passphrase, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $passphrase,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
        );
    }

    /** @return array{0: string, 1: string} [state, header] */
    private function initPush(string $key): array
    {
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

        return [$state[0], $state[1]];
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
