<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Services\Backup\BackupArtifact;
use Illuminate\Support\Facades\Storage;
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
    /** Disk path prefix to archive (e.g. "vault", "photos"). */
    abstract protected function prefix(): string;

    /** Base name for the produced archive (e.g. "files", "gallery"). */
    abstract protected function name(): string;

    public function build(string $workDir): BackupArtifact
    {
        $disk = Storage::disk(config('files.disk'));
        $staging = $workDir.'/'.$this->name();
        @mkdir($staging, 0700, true);

        foreach ($disk->allFiles($this->prefix()) as $file) {
            $target = $staging.'/'.$file;
            @mkdir(dirname($target), 0700, true);
            $read = $disk->readStream($file);
            if ($read === null) {
                continue;
            }
            $write = fopen($target, 'wb');
            stream_copy_to_stream($read, $write);
            fclose($write);
            fclose($read);
        }

        $tarPath = $workDir.'/'.$this->name().'.tar';
        $archive = new PharData($tarPath);
        // buildFromDirectory keeps the prefix-relative layout; empty dir → empty tar.
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
}
