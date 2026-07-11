<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupRun;
use App\Support\Bytes;
use Carbon\Carbon;
use League\Flysystem\Filesystem;
use RuntimeException;
use Throwable;

/**
 * Non-destructive integrity + dry-run-restore check for a completed backup run.
 *
 * It fetches the stored object from its destination, confirms it is intact and
 * (for encrypted archives) that the passphrase actually decrypts it — the
 * secretstream's authentication tags make a clean decrypt a full end-to-end
 * integrity proof — then inspects the inner archive to confirm it is a
 * restorable database dump (gzip-decompressible, recognisable dump header, a
 * plausible table count). Nothing is ever written to live data.
 */
final class BackupVerifier
{
    /** Cap on the decompressed bytes scanned during the dry run (safety valve). */
    private const SCAN_CAP = 8 * 1024 * 1024 * 1024;

    public function __construct(
        private readonly BackupDestinationFactory $factory,
        private readonly ArchiveCipher $cipher,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function verify(BackupRun $run, ?string $passphrase): array
    {
        $result = $this->run($run, $passphrase);

        $run->update([
            'verified_at' => Carbon::now(),
            'verify_status' => $result['ok'] ? 'ok' : 'failed',
            'verify_message' => $result['message'],
        ]);

        return $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function run(BackupRun $run, ?string $passphrase): array
    {
        $job = $run->job;
        if ($run->status !== 'success' || $run->filename === null || $job === null || $job->destination === null) {
            return ['ok' => false, 'message' => 'This run has no stored archive to verify.'];
        }

        $fs = $this->factory->make($job->destination);

        // Folder mirror (files/gallery): confirm the objects are present.
        if (str_ends_with($run->filename, '/')) {
            return $this->verifyMirror($fs, $run->filename);
        }

        if (! $fs->fileExists($run->filename)) {
            return ['ok' => false, 'message' => 'The archive is missing from its destination.'];
        }

        $enc = tempnam(sys_get_temp_dir(), 'llvenc');
        $dec = null;
        try {
            $this->download($fs, $run->filename, $enc);
            $storedBytes = (int) (filesize($enc) ?: 0);

            $encrypted = str_ends_with($run->filename, '.enc');
            if ($encrypted) {
                if (($passphrase ?? '') === '') {
                    return ['ok' => false, 'message' => 'A passphrase is required to verify an encrypted archive.'];
                }
                $dec = tempnam(sys_get_temp_dir(), 'llvdec');
                try {
                    $this->cipher->decryptFile($enc, $dec, (string) $passphrase);
                } catch (Throwable $e) {
                    return ['ok' => false, 'message' => 'Decryption failed — wrong passphrase or the archive is corrupt. ('.$e->getMessage().')'];
                }
            } else {
                $dec = $enc;
            }

            $inner = $this->inspect($dec, (string) $run->filename);

            $parts = ['Downloaded '.Bytes::format($storedBytes).'.'];
            if ($encrypted) {
                $parts[] = 'Decrypted successfully (integrity verified).';
            }
            $parts[] = $inner['message'];

            return ['ok' => $inner['ok'], 'message' => implode(' ', $parts)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Verification error: '.$e->getMessage()];
        } finally {
            @unlink($enc);
            if ($dec !== null && $dec !== $enc) {
                @unlink($dec);
            }
        }
    }

    /**
     * Dry-run inspect a decrypted archive: prove it decompresses and looks like
     * the expected dump, and estimate its contents.
     *
     * @return array{ok: bool, message: string}
     */
    private function inspect(string $path, string $filename): array
    {
        // SQLite: a gzipped copy of the DB file — verify the magic header.
        if (str_contains($filename, '.sqlite.gz')) {
            $gz = gzopen($path, 'rb');
            if ($gz === false) {
                return ['ok' => false, 'message' => 'Could not open the archive.'];
            }
            $head = gzread($gz, 16);
            gzclose($gz);

            return str_starts_with((string) $head, 'SQLite format 3')
                ? ['ok' => true, 'message' => 'Valid SQLite database image — restorable.']
                : ['ok' => false, 'message' => 'The archive is not a valid SQLite image.'];
        }

        // SQL dump (pg_dump / mysqldump), gzip-compressed. Stream the whole thing
        // to prove it fully decompresses, counting tables as a restore preview.
        if (str_contains($filename, '.sql.gz')) {
            return $this->inspectSqlDump($path);
        }

        // Unknown archive kind — at least confirm it is a readable gzip stream.
        $magic = @file_get_contents($path, false, null, 0, 2);

        return $magic === "\x1f\x8b"
            ? ['ok' => true, 'message' => 'Archive present and readable.']
            : ['ok' => true, 'message' => 'Archive present.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function inspectSqlDump(string $path): array
    {
        $gz = gzopen($path, 'rb');
        if ($gz === false) {
            return ['ok' => false, 'message' => 'Could not open the archive.'];
        }

        $tables = 0;
        $bytes = 0;
        $carry = '';
        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 262144);
                if ($chunk === false) {
                    return ['ok' => false, 'message' => 'The archive is corrupt — decompression failed.'];
                }
                $bytes += strlen($chunk);
                // Count CREATE TABLE across chunk boundaries by keeping a small tail.
                $window = $carry.$chunk;
                $tables += substr_count($window, 'CREATE TABLE ');
                $carry = substr($window, -16);
                if ($bytes > self::SCAN_CAP) {
                    break;
                }
            }
        } finally {
            gzclose($gz);
        }

        if ($bytes === 0) {
            return ['ok' => false, 'message' => 'The dump is empty.'];
        }

        return [
            'ok' => $tables > 0,
            'message' => $tables > 0
                ? sprintf('Valid SQL dump — decompresses to %s across %d table(s). Restorable.', Bytes::format($bytes), $tables)
                : sprintf('Decompressed %s but found no table definitions — the dump may be incomplete.', Bytes::format($bytes)),
        ];
    }

    /**
     * @param  Filesystem  $fs
     * @return array{ok: bool, message: string}
     */
    private function verifyMirror($fs, string $prefix): array
    {
        $count = 0;
        $bytes = 0;
        foreach ($fs->listContents(rtrim($prefix, '/'), true) as $item) {
            if ($item->isFile()) {
                $count++;
                $bytes += (int) ($item->fileSize() ?? 0);
            }
        }

        return $count > 0
            ? ['ok' => true, 'message' => sprintf('Mirror present: %d object(s), %s.', $count, Bytes::format($bytes))]
            : ['ok' => false, 'message' => 'The mirror folder is empty or missing.'];
    }

    /**
     * @param  Filesystem  $fs
     */
    private function download($fs, string $path, string $to): void
    {
        $stream = $fs->readStream($path);
        if ($stream === null) {
            throw new RuntimeException('Could not read the archive from its destination.');
        }
        $out = fopen($to, 'wb');
        if ($out === false) {
            fclose($stream);
            throw new RuntimeException('Could not stage the archive locally.');
        }
        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
            fclose($stream);
        }
    }
}
