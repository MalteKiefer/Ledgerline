<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Services\Backup\BackupArtifact;
use App\Support\ArchiveName;
use App\Support\BlobStore;
use PharData;
use RuntimeException;

/**
 * Archives every object under a prefix of the files disk into a gzipped tar.
 *
 * Files are streamed from the (possibly remote) disk into a local staging
 * directory preserving their relative paths, then packed — so memory stays
 * bounded regardless of how many objects or how large they are.
 */
abstract class DiskArchiveSource implements BackupSource
{
    /** Disk path prefix to archive (e.g. "files", "photos"). */
    abstract protected function prefix(): string;

    /** Base name for the produced archive (e.g. "files", "gallery"). */
    abstract protected function name(): string;

    public function build(string $workDir): BackupArtifact
    {
        $disk = BlobStore::disk();
        $staging = $workDir.'/'.$this->name();
        $this->makeDir($staging);

        $staged = 0;
        foreach ($disk->allFiles($this->prefix()) as $file) {
            // Object keys come from the disk; refuse any that would escape the
            // staging directory (defence-in-depth against a crafted key).
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
            $copied = stream_copy_to_stream($read, $write);
            fclose($write);
            fclose($read);
            if ($copied === false) {
                throw new RuntimeException('Failed to copy '.$file.' into the archive.');
            }
            $staged++;
        }

        // PharData refuses to build from an empty directory, so an empty source
        // (e.g. a files disk with nothing yet) would throw. Stage a marker so the
        // archive is always valid — restore just ignores it.
        if ($staged === 0) {
            file_put_contents($staging.'/.ledgerline-empty', "This backup source was empty at backup time.\n");
        }

        $tarPath = $workDir.'/'.$this->name().'.tar';
        $archive = new PharData($tarPath);
        $archive->buildFromDirectory($staging);
        $archive->compress(\Phar::GZ);
        unset($archive);

        $gzPath = $tarPath.'.gz';
        if (! is_file($gzPath)) {
            throw new RuntimeException('Failed to compress archive for '.$this->name().'.');
        }
        @unlink($tarPath);

        return new BackupArtifact($gzPath, 'tar.gz');
    }

    private function makeDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create staging directory: '.$dir);
        }
    }
}
