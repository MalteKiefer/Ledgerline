<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Export;
use App\Models\FileFolder;
use App\Models\Photo;
use App\Models\StoredFile;
use App\Services\Gallery\PhotoExporter;
use App\Support\DiskTempFile;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use RuntimeException;
use ZipArchive;

/**
 * Builds an export's archive file(s) on the files disk. Entries are gathered per
 * source (gallery photos or files/folders) into a shared list, then written in
 * the export's chosen format:
 *
 *  - 'zip' (default): streamed through a local temp zip and uploaded. When a
 *    configured maximum size is set, the archive is split into several parts so
 *    no single zip exceeds it.
 *  - 'tar' | 'targz' | 'tarbz2': a single PharData tar (optionally gzip/bzip2
 *    compressed). Tar cannot be incrementally size-split like zip, so it is
 *    always one part regardless of $maxBytes.
 *
 * @phpstan-type Part array{name: string, path: string, size: int}
 */
class ExportArchiver
{
    public function __construct(private readonly PhotoExporter $photoExporter) {}

    /**
     * @return list<Part>
     */
    public function build(Export $export, int $maxBytes): array
    {
        $disk = Storage::disk(config('files.disk'));

        return match ($export->format ?: 'zip') {
            'tar', 'targz', 'tarbz2' => $this->buildTar($export, (string) $export->format, $disk),
            default => $this->buildZip($export, $maxBytes, $disk),
        };
    }

    /**
     * @return list<Part>
     */
    private function buildZip(Export $export, int $maxBytes, Filesystem $disk): array
    {
        /** @var list<Part> $parts */
        $parts = [];
        $zip = null;
        $zipTmp = null;
        $curSize = 0;
        $usedNames = [];
        $partTemps = [];
        $partIndex = 0;

        $flush = function () use (&$zip, &$zipTmp, &$parts, &$partIndex, &$partTemps, &$curSize, &$usedNames, $export, $disk): void {
            if ($zip === null) {
                return;
            }
            $zip->close();
            $partIndex++;
            $size = (int) (filesize($zipTmp) ?: 0);
            $path = "exports/{$export->user_id}/{$export->id}/part-{$partIndex}.zip";
            $stream = fopen($zipTmp, 'r');
            $disk->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $parts[] = ['name' => "part-{$partIndex}", 'path' => $path, 'size' => $size];

            @unlink($zipTmp);
            foreach ($partTemps as $t) {
                @unlink($t);
            }
            $partTemps = [];
            $zip = null;
            $curSize = 0;
            $usedNames = [];
        };

        $open = function () use (&$zip, &$zipTmp): void {
            $zipTmp = tempnam(sys_get_temp_dir(), 'exp-zip').'.zip';
            $zip = new ZipArchive;
            $zip->open($zipTmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        };

        $open();
        foreach ($this->entries($export, $disk) as [$name, $localPath, $size]) {
            if ($maxBytes > 0 && $curSize > 0 && ($curSize + $size) > $maxBytes) {
                $flush();
                $open();
            }
            $entry = $this->uniqueName($usedNames, $name);
            $zip->addFile($localPath, $entry);
            $usedNames[$entry] = true;
            $partTemps[] = $localPath;
            $curSize += $size;
        }
        $flush();

        return $this->nameParts($parts, $export->title);
    }

    /**
     * Build a single tar archive (optionally gzip/bzip2 compressed) from the same
     * entries the zip path gathers. Tar cannot be size-split incrementally, so the
     * whole selection is written into one archive and returned as one part.
     *
     * @return list<Part>
     */
    private function buildTar(Export $export, string $format, Filesystem $disk): array
    {
        // bzip2 needs the bz2 extension; fall back to gzip when it is missing.
        if ($format === 'tarbz2' && ! extension_loaded('bz2')) {
            $format = 'targz';
        }

        [$compression, $ext] = match ($format) {
            'targz' => [Phar::GZ, 'tar.gz'],
            'tarbz2' => [Phar::BZ2, 'tar.bz2'],
            default => [null, 'tar'],
        };

        // PharData refuses to open an existing file, so start from a fresh path.
        $tmpTar = tempnam(sys_get_temp_dir(), 'exp-tar');
        @unlink($tmpTar);
        $tmpTar .= '.tar';

        /** @var list<string> $srcTemps entry temp files pulled from the disk */
        $srcTemps = [];
        // Every path we may need to clean up (the .tar and any compressed sibling).
        $archiveTemps = [$tmpTar];

        try {
            $phar = new PharData($tmpTar);
            $usedNames = [];
            foreach ($this->entries($export, $disk) as [$name, $localPath, $size]) {
                $srcTemps[] = $localPath;
                $entry = $this->uniqueName($usedNames, $name);
                $phar->addFile($localPath, $entry);
                $usedNames[$entry] = true;
            }

            $archivePath = $tmpTar;
            if ($compression !== null) {
                // compress() writes a sibling file (e.g. .tar.gz) beside the .tar.
                $phar->compress($compression, '.'.$ext);
                $archivePath = preg_replace('/\.tar$/', '.'.$ext, $tmpTar) ?? $tmpTar;
                $archiveTemps[] = $archivePath;
            }
            // Release the Phar handle before reading/unlinking the files.
            unset($phar);

            if (! is_file($archivePath)) {
                throw new RuntimeException("Failed to build {$ext} archive.");
            }

            $size = (int) (filesize($archivePath) ?: 0);
            $path = "exports/{$export->user_id}/{$export->id}/export.{$ext}";
            $stream = fopen($archivePath, 'r');
            $disk->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $base = $this->safeName($export->title !== '' ? $export->title : 'export');

            return [['name' => "{$base}.{$ext}", 'path' => $path, 'size' => $size]];
        } finally {
            foreach (array_merge($archiveTemps, $srcTemps) as $t) {
                @unlink($t);
            }
        }
    }

    /**
     * @param  list<Part>  $parts
     * @return list<Part>
     */
    private function nameParts(array $parts, string $title): array
    {
        $base = $this->safeName($title !== '' ? $title : 'export');
        $total = count($parts);

        foreach ($parts as $i => &$part) {
            $part['name'] = $total > 1 ? sprintf('%s (%d of %d).zip', $base, $i + 1, $total) : "{$base}.zip";
        }

        return $parts;
    }

    /**
     * @return Generator<array{0: string, 1: string, 2: int}>
     */
    private function entries(Export $export, Filesystem $disk): Generator
    {
        $payload = $export->payload ?? [];

        return $export->source === 'gallery'
            ? $this->galleryEntries($payload, (string) $export->variant, $disk)
            : $this->fileEntries($payload, $disk);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Generator<array{0: string, 1: string, 2: int}>
     */
    private function galleryEntries(array $payload, string $variant, Filesystem $disk): Generator
    {
        $ids = $payload['photo_ids'] ?? [];

        foreach (Photo::query()->whereIn('id', $ids)->get() as $photo) {
            if ($variant === 'edited') {
                foreach ($this->photoExporter->editedFiles($photo) as $file) {
                    yield [$file['name'], $file['path'], (int) (filesize($file['path']) ?: 0)];
                }

                continue;
            }

            $tmp = $this->localCopy($disk, $photo->disk_path);
            yield [(string) $photo->name, $tmp, (int) ($photo->size ?: filesize($tmp))];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Generator<array{0: string, 1: string, 2: int}>
     */
    private function fileEntries(array $payload, Filesystem $disk): Generator
    {
        foreach (StoredFile::query()->whereIn('id', $payload['file_ids'] ?? [])->get() as $file) {
            yield $this->fileEntry($file, (string) $file->name, $disk);
        }

        foreach (FileFolder::query()->whereIn('id', $payload['folder_ids'] ?? [])->get() as $folder) {
            yield from $this->folderEntries($folder, (string) $folder->name, $disk);
        }
    }

    /**
     * @return Generator<array{0: string, 1: string, 2: int}>
     */
    private function folderEntries(FileFolder $folder, string $prefix, Filesystem $disk): Generator
    {
        foreach (StoredFile::query()->where('file_folder_id', $folder->id)->get() as $file) {
            yield $this->fileEntry($file, $prefix.'/'.$file->name, $disk);
        }

        foreach (FileFolder::query()->where('parent_id', $folder->id)->get() as $sub) {
            yield from $this->folderEntries($sub, $prefix.'/'.$sub->name, $disk);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function fileEntry(StoredFile $file, string $name, Filesystem $disk): array
    {
        $tmp = $this->localCopy($disk, 'files/'.$file->blob);

        return [$name, $tmp, (int) ($file->size ?: filesize($tmp))];
    }

    private function localCopy(Filesystem $disk, string $path): string
    {
        return DiskTempFile::pull($disk, $path, 'exp-src');
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueName(array $used, string $name): string
    {
        $name = $this->safePath($name);
        if (! isset($used[$name])) {
            return $name;
        }

        $dot = strrpos($name, '.');
        $base = $dot > 0 ? substr($name, 0, $dot) : $name;
        $ext = $dot > 0 ? substr($name, $dot) : '';

        $i = 2;
        while (isset($used[$base.'_'.$i.$ext])) {
            $i++;
        }

        return $base.'_'.$i.$ext;
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $name) ?? 'export';

        return trim($name) !== '' ? trim($name) : 'export';
    }

    /**
     * Sanitise a zip member path against Zip-Slip: normalise backslashes, split on
     * "/", scrub each segment (also dropping "", "." and ".."), and rejoin. The
     * result can never contain a ".." segment or a leading "/", so no member can
     * escape the extraction root — however naive the extractor.
     */
    private function safePath(string $name): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $name)) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $segments[] = $this->safeName($segment);
        }

        return $segments === [] ? 'export' : implode('/', $segments);
    }
}
