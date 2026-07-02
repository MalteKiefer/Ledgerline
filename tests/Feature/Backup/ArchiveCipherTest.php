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
}
