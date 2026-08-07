<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\ArchiveCipher;
use Tests\TestCase;

class ArchiveCipherTest extends TestCase
{
    private function tmp(string $name): string
    {
        return sys_get_temp_dir().'/llbk_'.uniqid().'_'.$name;
    }

    public function test_it_round_trips_a_file(): void
    {
        $cipher = new ArchiveCipher;
        $plain = $this->tmp('plain');
        $enc = $this->tmp('enc');
        $out = $this->tmp('out');
        // A payload larger than one chunk to exercise streaming.
        file_put_contents($plain, random_bytes(200_000));

        $cipher->encryptFile($plain, $enc, 'correct horse battery staple');
        $cipher->decryptFile($enc, $out, 'correct horse battery staple');

        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', $out));
        $this->assertNotSame(file_get_contents($plain), file_get_contents($enc));

        foreach ([$plain, $enc, $out] as $f) {
            @unlink($f);
        }
    }

    public function test_a_wrong_passphrase_fails(): void
    {
        $cipher = new ArchiveCipher;
        $plain = $this->tmp('plain');
        $enc = $this->tmp('enc');
        $out = $this->tmp('out');
        file_put_contents($plain, 'secret payload');

        $cipher->encryptFile($plain, $enc, 'right');

        $this->expectException(\RuntimeException::class);
        try {
            $cipher->decryptFile($enc, $out, 'wrong');
        } finally {
            foreach ([$plain, $enc, $out] as $f) {
                @unlink($f);
            }
        }
    }

    public function test_a_tampered_kdf_opslimit_is_rejected_before_the_kdf_runs(): void
    {
        $cipher = new ArchiveCipher;
        $plain = $this->tmp('plain');
        $enc = $this->tmp('enc');
        $out = $this->tmp('out');
        file_put_contents($plain, 'payload');
        $cipher->encryptFile($plain, $enc, 'pw');

        // V2 header: magic(6) | opslimit(u32) | memlimit(u32) | salt | stream-header.
        // Overwrite the opslimit u32 (offset 6..9) with ~4.29 billion Argon2id passes.
        $bytes = (string) file_get_contents($enc);
        $bytes = substr_replace($bytes, pack('N', 0xFFFFFFFF), 6, 4);
        file_put_contents($enc, $bytes);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('opslimit');
            $cipher->decryptFile($enc, $out, 'pw');
        } finally {
            foreach ([$plain, $enc, $out] as $f) {
                @unlink($f);
            }
        }
    }

    public function test_a_tampered_kdf_memlimit_is_rejected_before_the_kdf_runs(): void
    {
        $cipher = new ArchiveCipher;
        $plain = $this->tmp('plain');
        $enc = $this->tmp('enc');
        $out = $this->tmp('out');
        file_put_contents($plain, 'payload');
        $cipher->encryptFile($plain, $enc, 'pw');

        // Overwrite the memlimit u32 (offset 10..13) with ~4 GiB.
        $bytes = (string) file_get_contents($enc);
        $bytes = substr_replace($bytes, pack('N', 0xFFFFFFFF), 10, 4);
        file_put_contents($enc, $bytes);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('memlimit');
            $cipher->decryptFile($enc, $out, 'pw');
        } finally {
            foreach ([$plain, $enc, $out] as $f) {
                @unlink($f);
            }
        }
    }

    public function test_an_oversized_chunk_length_is_rejected(): void
    {
        $cipher = new ArchiveCipher;
        $plain = $this->tmp('plain');
        $enc = $this->tmp('enc');
        $out = $this->tmp('out');
        file_put_contents($plain, 'payload'); // single chunk
        $cipher->encryptFile($plain, $enc, 'pw');

        // The first framed chunk-length u32 sits after
        // magic(6)+ops(4)+mem(4)+salt+stream-header. Overwrite it with ~4 GiB so a
        // naive fread($in,$len) would try a multi-GB allocation.
        $offset = 6 + 4 + 4
            + SODIUM_CRYPTO_PWHASH_SALTBYTES
            + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        $bytes = (string) file_get_contents($enc);
        $bytes = substr_replace($bytes, pack('N', 0xFFFFFFFF), $offset, 4);
        file_put_contents($enc, $bytes);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('chunk length too large');
            $cipher->decryptFile($enc, $out, 'pw');
        } finally {
            foreach ([$plain, $enc, $out] as $f) {
                @unlink($f);
            }
        }
    }

    public function test_a_truncated_archive_is_rejected(): void
    {
        $cipher = new ArchiveCipher;
        $plain = $this->tmp('plain');
        $enc = $this->tmp('enc');
        $out = $this->tmp('out');
        // Multi-chunk payload so lopping off the tail removes the FINAL marker.
        file_put_contents($plain, random_bytes(200_000));
        $cipher->encryptFile($plain, $enc, 'pw');

        // Drop the last 4 KiB — a truncated-but-otherwise-valid prefix.
        $full = file_get_contents($enc);
        file_put_contents($enc, substr($full, 0, strlen($full) - 4096));

        try {
            $this->expectException(\RuntimeException::class);
            $cipher->decryptFile($enc, $out, 'pw');
        } finally {
            foreach ([$plain, $enc, $out] as $f) {
                @unlink($f);
            }
        }
    }
}
