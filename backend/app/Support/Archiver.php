<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Create and extract archives for the Files module.
 *
 * CREATE runs on TRUSTED input (the user's own files) and is safe to call inline:
 * zip via PHP's ZipArchive (optional AES-256 password), tar.gz/tar.xz/7z via the
 * `tar`/`gzip`/`xz`/`7z` binaries (array-argv through BinaryProcess — no shell,
 * no injection). Only zip and 7z support a password.
 *
 * EXTRACT runs on UNTRUSTED input (an uploaded archive) and MUST run on the worker
 * only (a decompression bomb would otherwise block a web/Octane worker). It is
 * hardened against: zip-slip (every entry path is confined under the destination),
 * bomb blow-up (entry-count + total-uncompressed-byte caps, checked after a bounded
 * extract into an empty temp dir), and runaway CPU (per-process timeout). The
 * matching binaries (p7zip, unrar, xz, zstd, bzip2, unzip, tar) are in the image.
 */
final class Archiver
{
    /** Formats we can produce. */
    public const CREATE_FORMATS = ['zip', 'tar.gz', 'tar.xz', '7z'];

    /** Formats that accept a password on create. */
    public const PASSWORD_FORMATS = ['zip', '7z'];

    private const MAX_ENTRIES = 20000;

    private const MAX_TOTAL_BYTES = 20 * 1024 * 1024 * 1024; // 20 GiB uncompressed

    private const TIMEOUT = 1800;

    /**
     * Build $outPath from a map of archive-entry name → absolute local source path.
     *
     * @param  array<string, string>  $entries  entryRelName => absSourcePath
     * @param  int|null  $level  compression level 0..9 (null = tool default)
     */
    public static function create(array $entries, string $format, ?int $level, ?string $password, string $outPath): void
    {
        if (! in_array($format, self::CREATE_FORMATS, true)) {
            throw new RuntimeException('Unsupported archive format: '.$format);
        }
        if ($password !== null && $password !== '' && ! in_array($format, self::PASSWORD_FORMATS, true)) {
            throw new RuntimeException($format.' does not support a password.');
        }
        $lvl = $level === null ? null : max(0, min(9, $level));

        if ($format === 'zip') {
            self::createZip($entries, $lvl, $password, $outPath);

            return;
        }

        // tar.*/7z work from a staging directory that mirrors the entry structure
        // (hardlink when same-filesystem, else copy) so the binary sees clean
        // relative names. RAII-cleaned on every exit.
        $staging = self::makeTempDir('llarc-stage');
        try {
            foreach ($entries as $rel => $src) {
                $safe = ArchiveName::safe($staging, $rel);
                @mkdir(dirname($safe), 0700, true);
                if (! @link($src, $safe) && ! @copy($src, $safe)) {
                    throw new RuntimeException('Could not stage '.$rel.' for archiving.');
                }
            }
            match ($format) {
                'tar.gz' => self::runTarCompress($staging, 'gzip', $lvl, $outPath, '.gz'),
                'tar.xz' => self::runTarCompress($staging, 'xz', $lvl, $outPath, '.xz'),
                '7z' => self::run7zCreate($staging, $lvl, $password, $outPath),
                default => throw new RuntimeException('unreachable'),
            };
        } finally {
            self::rmrf($staging);
        }
    }

    /**
     * Extract an untrusted archive into a fresh temp directory and return the
     * safe files found (relative path → absolute temp path). The caller (worker)
     * persists them; the temp dir is the caller's to clean via the returned root.
     *
     * @return array{root: string, files: array<string, string>}
     */
    public static function extract(string $archivePath, ?string $password): array
    {
        $format = self::detectFormat($archivePath);
        if ($format === null) {
            throw new RuntimeException('Unsupported or unrecognised archive.');
        }
        $dest = self::makeTempDir('llarc-out');
        try {
            self::extractInto($archivePath, $format, $password, $dest);

            // Walk the result: confine every path under $dest (zip-slip), enforce
            // the entry-count + total-byte caps (bomb blow-up), collect files only.
            $files = [];
            $total = 0;
            $count = 0;
            $destReal = realpath($dest) ?: $dest;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dest, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($it as $info) {
                /** @var \SplFileInfo $info */
                if (! $info->isFile() || $info->isLink()) {
                    continue; // skip symlinks/special (a link could point outside)
                }
                $abs = (string) $info->getRealPath();
                if ($abs === '' || ! str_starts_with($abs, $destReal.DIRECTORY_SEPARATOR)) {
                    continue; // escaped the destination → drop it
                }
                if (++$count > self::MAX_ENTRIES) {
                    throw new RuntimeException('Archive has too many files.');
                }
                $total += $info->getSize();
                if ($total > self::MAX_TOTAL_BYTES) {
                    throw new RuntimeException('Archive is too large when extracted.');
                }
                $rel = ltrim(substr($abs, strlen($destReal)), DIRECTORY_SEPARATOR);
                $files[$rel] = $abs;
            }

            return ['root' => $dest, 'files' => $files];
        } catch (\Throwable $e) {
            self::rmrf($dest);
            throw $e;
        }
    }

    /** Recognised archive format from a filename, or null. Longest suffix first. */
    public static function detectFormat(string $name): ?string
    {
        $n = strtolower($name);
        foreach ([
            'tar.gz' => 'tar.gz', 'tgz' => 'tar.gz',
            'tar.xz' => 'tar.xz', 'txz' => 'tar.xz',
            'tar.bz2' => 'tar.bz2', 'tbz2' => 'tar.bz2', 'tbz' => 'tar.bz2',
            'tar.zst' => 'tar.zst', 'tzst' => 'tar.zst',
            'tar' => 'tar', 'zip' => 'zip', '7z' => '7z', 'rar' => 'rar',
            'gz' => 'gz', 'bz2' => 'bz2', 'xz' => 'xz', 'zst' => 'zst',
        ] as $ext => $fmt) {
            if (str_ends_with($n, '.'.$ext)) {
                return $fmt;
            }
        }

        return null;
    }

    public static function isArchive(string $name): bool
    {
        return self::detectFormat($name) !== null;
    }

    // ---- create helpers ----

    /** @param  array<string, string>  $entries */
    private static function createZip(array $entries, ?int $level, ?string $password, string $outPath): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($outPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the zip archive.');
        }
        if ($password !== null && $password !== '') {
            $zip->setPassword($password);
        }
        foreach ($entries as $rel => $src) {
            $entry = ltrim(str_replace('\\', '/', $rel), '/');
            if ($entry === '' || str_contains($entry, '..')) {
                continue;
            }
            if (! $zip->addFile($src, $entry)) {
                $zip->close();
                throw new RuntimeException('Could not add '.$rel.' to the zip.');
            }
            if ($level === 0) {
                $zip->setCompressionName($entry, \ZipArchive::CM_STORE);
            }
            if ($password !== null && $password !== '') {
                $zip->setEncryptionName($entry, \ZipArchive::EM_AES_256);
            }
        }
        if (! $zip->close()) {
            throw new RuntimeException('Could not finalise the zip archive.');
        }
    }

    /** tar the staging dir, then compress the tar with gzip/xz at $level → $outPath. */
    private static function runTarCompress(string $staging, string $compressor, ?int $level, string $outPath, string $suffix): void
    {
        $tar = $outPath.'.tar';
        $r = BinaryProcess::runCapture(['tar', '-cf', $tar, '-C', $staging, '.'], self::TIMEOUT);
        if (! $r['ok']) {
            @unlink($tar);
            throw new RuntimeException('tar failed: '.self::tail($r['err']));
        }
        $argv = $compressor === 'xz'
            ? ['xz', '-z', '-f', '-T0', '-'.($level ?? 6), $tar]
            : ['gzip', '-f', '-'.($level ?? 6), $tar];
        $c = BinaryProcess::runCapture($argv, self::TIMEOUT);
        if (! $c['ok']) {
            @unlink($tar);
            @unlink($tar.$suffix);
            throw new RuntimeException($compressor.' failed: '.self::tail($c['err']));
        }
        // gzip/xz replace <tar> with <tar><suffix>; move it to the requested path.
        if (! @rename($tar.$suffix, $outPath)) {
            throw new RuntimeException('Could not finalise the archive.');
        }
    }

    private static function run7zCreate(string $staging, ?int $level, ?string $password, string $outPath): void
    {
        // 7z's "add" appends to an existing archive; the caller pre-creates an empty
        // 0-byte temp file, which 7z treats as a corrupt existing archive and bails
        // on (leaving a 0-byte output). Remove it so 7z creates a fresh archive.
        @unlink($outPath);
        $argv = ['7z', 'a', '-t7z', '-mx='.($level ?? 5), '-bd', '-y'];
        if ($password !== null && $password !== '') {
            $argv[] = '-p'.$password;
            $argv[] = '-mhe=on'; // encrypt headers (hide file names)
        }
        $argv[] = $outPath;
        $argv[] = '.';
        $r = BinaryProcess::runCapture($argv, self::TIMEOUT, $staging);
        if (! $r['ok'] || ! is_file($outPath)) {
            throw new RuntimeException('7z failed: '.self::tail($r['err']));
        }
    }

    // ---- extract helpers ----

    private static function extractInto(string $archive, string $format, ?string $password, string $dest): void
    {
        if ($format === 'zip') {
            self::extractZip($archive, $password, $dest);

            return;
        }
        if (in_array($format, ['tar', 'tar.gz', 'tar.xz', 'tar.bz2', 'tar.zst'], true)) {
            // GNU tar auto-detects the compression and strips leading slashes.
            $r = BinaryProcess::runCapture(['tar', '-xf', $archive, '-C', $dest, '--no-same-owner', '--no-same-permissions'], self::TIMEOUT);
            if (! $r['ok']) {
                throw new RuntimeException('tar extract failed: '.self::tail($r['err']));
            }

            return;
        }
        if (in_array($format, ['gz', 'bz2', 'xz', 'zst'], true)) {
            self::extractSingle($archive, $format, $dest);

            return;
        }
        // 7z handles 7z, rar (RAR4 fully, RAR5 best-effort — no unrar in Alpine),
        // and zip/tar/etc. as a fallback.
        $argv = ['7z', 'x', '-y', '-bd', '-o'.$dest];
        $argv[] = $password !== null && $password !== '' ? '-p'.$password : '-p';
        $argv[] = $archive;
        $r = BinaryProcess::runCapture($argv, self::TIMEOUT);
        if (! $r['ok']) {
            throw new RuntimeException('extract failed: '.self::tail($r['err']));
        }
    }

    /** Per-entry zip extraction confined under $dest (never ZipArchive::extractTo, which is not slip-safe). */
    private static function extractZip(string $archive, ?string $password, string $dest): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Could not open the zip archive.');
        }
        if ($password !== null && $password !== '') {
            $zip->setPassword($password);
        }
        // Pre-check the declared uncompressed total (cheap zip-bomb guard).
        $declared = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if (is_array($st)) {
                $declared += (int) ($st['size'] ?? 0);
            }
            if ($i + 1 > self::MAX_ENTRIES || $declared > self::MAX_TOTAL_BYTES) {
                $zip->close();
                throw new RuntimeException('Archive is too large or has too many files.');
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || $name === '' || str_ends_with($name, '/')) {
                continue; // directory entry — created lazily below
            }
            $target = ArchiveName::safe($dest, $name); // throws on ../absolute/NUL
            @mkdir(dirname($target), 0700, true);
            $in = $zip->getStream($name);
            $out = @fopen($target, 'wb');
            if (! is_resource($in) || ! is_resource($out)) {
                if (is_resource($in)) {
                    fclose($in);
                }
                if (is_resource($out)) {
                    fclose($out);
                }
                $zip->close();
                throw new RuntimeException('Could not extract '.$name.' (wrong password?).');
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $zip->close();
    }

    /** Decompress a single-stream file (foo.gz → foo) into $dest. */
    private static function extractSingle(string $archive, string $format, string $dest): void
    {
        // Copy into the destination, then decompress in place (the tool drops the
        // extension) — avoids a stdout redirect (which array-argv can't express).
        $copy = $dest.'/'.basename($archive);
        if (! @copy($archive, $copy)) {
            throw new RuntimeException('Could not stage the archive for decompression.');
        }
        $argv = match ($format) {
            'gz' => ['gzip', '-d', '-f', $copy],
            'bz2' => ['bzip2', '-d', '-f', $copy],
            'xz' => ['xz', '-d', '-f', $copy],
            'zst' => ['zstd', '-d', '-f', '--rm', $copy],
            default => throw new RuntimeException('unreachable'),
        };
        $r = BinaryProcess::runCapture($argv, self::TIMEOUT);
        // The source copy is removed by -d (gzip/bzip2/xz) or --rm (zstd); belt-and-braces.
        @unlink($copy);
        if (! $r['ok']) {
            throw new RuntimeException('decompress failed: '.self::tail($r['err']));
        }
    }

    // ---- misc ----

    private static function makeTempDir(string $prefix): string
    {
        $base = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(8));
        if (! @mkdir($base, 0700, true) && ! is_dir($base)) {
            throw new RuntimeException('Could not create a temp directory.');
        }

        return $base;
    }

    public static function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            @unlink($dir);

            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            $f->isDir() && ! $f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private static function tail(string $s): string
    {
        $s = trim($s);

        return strlen($s) > 200 ? substr($s, -200) : $s;
    }
}
