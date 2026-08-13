<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Services\Backup\BackupArtifact;
use App\Support\ArchiveName;
use App\Support\BinaryProcess;
use App\Support\BlobStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Archives every object under a prefix of the files disk into a gzipped tar.
 *
 * The tar is built by the streaming `tar` binary (memory-flat regardless of how
 * many objects or how large they are) — NOT PharData, which buffers the whole
 * archive in memory and OOM-kills the worker on a large set (e.g. a big gallery),
 * leaving the run to be reaped as "Interrupted (no progress)".
 *
 * On a LOCAL files disk the tar reads the source directory directly (no staging
 * copy at all). On a remote disk (S3), objects are first streamed one at a time
 * into a local staging directory, then tarred.
 */
abstract class DiskArchiveSource implements BackupSource
{
    /** Disk path prefix to archive (e.g. "files", "gallery"). */
    abstract protected function prefix(): string;

    /** Base name for the produced archive (e.g. "files", "gallery"). */
    abstract protected function name(): string;

    /** When set (incremental mode), only include objects modified at or after this unix ts. */
    private ?int $sinceTs = null;

    /** tar can run for a while on a large first-full archive (gzip is CPU-bound). */
    private const TAR_TIMEOUT = 7200;

    public function onlySince(int $ts): void
    {
        $this->sinceTs = $ts;
    }

    /**
     * The files-disk prefix this source covers (e.g. "files", "gallery"). Public so
     * the manager can mirror the source object-by-object to the destination instead
     * of building a giant tar — memory-flat, delta-only, and never chain-dependent.
     */
    public function diskPrefix(): string
    {
        return $this->prefix();
    }

    public function build(string $workDir): BackupArtifact
    {
        $disk = BlobStore::disk();
        $gzPath = $workDir.'/'.$this->name().'.tar.gz';

        // Collect the object keys to archive (incremental filters by mtime).
        $keys = [];
        foreach ($disk->allFiles($this->prefix()) as $file) {
            if ($this->sinceTs !== null) {
                $mtime = (int) $disk->lastModified($file);
                if ($mtime > 0 && $mtime < $this->sinceTs) {
                    continue;
                }
            }
            $keys[] = $file;
        }

        $localRoot = $this->localRoot();
        if ($localRoot !== null) {
            // Local disk: tar straight from the source tree — no staging copy.
            $this->tarKeys($localRoot, $keys, $gzPath, $workDir);
        } else {
            // Remote disk: stage each object locally (bounded memory), then tar the dir.
            $staging = $workDir.'/'.$this->name();
            $this->makeDir($staging);
            $staged = [];
            foreach ($keys as $file) {
                $real = ArchiveName::safe($staging, $file);
                $this->makeDir(dirname($real));
                $read = $disk->readStream($file);
                if ($read === null) {
                    throw new RuntimeException('Could not read '.$file.' for backup.');
                }
                $write = fopen($real, 'wb');
                if ($write === false) {
                    fclose($read);
                    throw new RuntimeException('Could not stage '.$file.' for backup.');
                }
                if (stream_copy_to_stream($read, $write) === false) {
                    fclose($write);
                    fclose($read);
                    throw new RuntimeException('Failed to copy '.$file.' into the archive.');
                }
                fclose($write);
                fclose($read);
                $staged[] = $file; // key path preserved under $staging
            }
            $this->tarKeys($staging, $staged, $gzPath, $workDir);
        }

        if (! is_file($gzPath)) {
            throw new RuntimeException('Failed to build archive for '.$this->name().'.');
        }

        return new BackupArtifact($gzPath, 'tar.gz');
    }

    /**
     * Build a gzipped tar of the given keys, relative to $root, via the streaming
     * `tar` binary (memory-flat). An empty set still produces a valid archive
     * (a single marker file) so restore never trips over a missing member.
     *
     * @param  list<string>  $keys
     */
    private function tarKeys(string $root, array $keys, string $gzPath, string $workDir): void
    {
        if ($keys === []) {
            $markerDir = $workDir.'/'.$this->name().'-empty';
            $this->makeDir($markerDir);
            file_put_contents($markerDir.'/.ledgerline-empty', "This backup source was empty at backup time.\n");
            $out = BinaryProcess::run(['tar', '-czf', $gzPath, '-C', $workDir, $this->name().'-empty'], self::TAR_TIMEOUT);
            if ($out === null && ! is_file($gzPath)) {
                throw new RuntimeException('tar failed for empty '.$this->name().'.');
            }

            return;
        }

        // Pass the file list via a temp list file (-T) so a huge set never hits an
        // argv length limit and tar streams it directly. Each entry is prefixed with
        // ./ so a key can never be read as a tar option (argv-flag smuggling), and any
        // key with a newline/CR/NUL or a .. segment is dropped (would corrupt the list
        // or escape the root). Keys are server-generated UUID paths, so this is
        // defence-in-depth, not an expected case.
        $safe = [];
        foreach ($keys as $k) {
            if ($k === '' || preg_match('/[\r\n\0]/', $k) === 1 || str_contains($k, '..') || str_starts_with($k, '/')) {
                continue;
            }
            $safe[] = './'.$k;
        }
        if ($safe === []) {
            // Everything filtered out (shouldn't happen) → treat as empty.
            $this->tarKeys($root, [], $gzPath, $workDir);

            return;
        }
        $listFile = $workDir.'/.tarlist-'.$this->name();
        file_put_contents($listFile, implode("\n", $safe)."\n");
        try {
            $out = BinaryProcess::run(['tar', '-czf', $gzPath, '-C', $root, '-T', $listFile], self::TAR_TIMEOUT);
        } finally {
            @unlink($listFile);
        }
        if ($out === null && ! is_file($gzPath)) {
            throw new RuntimeException('tar failed for '.$this->name().'.');
        }
    }

    /** Absolute root of the files disk if it is a local filesystem, else null. */
    private function localRoot(): ?string
    {
        $name = config('files.disk');
        $name = is_string($name) && $name !== '' ? $name : 'local';
        if (config('filesystems.disks.'.$name.'.driver') !== 'local') {
            return null;
        }
        $root = config('filesystems.disks.'.$name.'.root');
        if (is_string($root) && $root !== '' && is_dir($root)) {
            return $root;
        }
        // Fall back to the adapter's path() if the config root is unusual.
        $disk = Storage::disk($name);

        return method_exists($disk, 'path') ? rtrim($disk->path(''), '/') : null;
    }

    private function makeDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create staging directory: '.$dir);
        }
    }
}
