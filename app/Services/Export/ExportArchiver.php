<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Export;
use App\Models\FileFolder;
use App\Models\Photo;
use App\Models\StoredFile;
use App\Services\Gallery\PhotoExporter;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Builds an export's zip file(s) on the files disk. Entries are gathered per
 * source (gallery photos or files/folders), streamed through a local temp zip
 * and uploaded. When a configured maximum size is set, the archive is split into
 * several parts so no single zip exceeds it.
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
        $tmp = tempnam(sys_get_temp_dir(), 'exp-src');
        $stream = $disk->readStream($path);
        try {
            file_put_contents($tmp, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $tmp;
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueName(array $used, string $name): string
    {
        $name = ltrim(str_replace(['\\', '../'], ['/', ''], $name), '/');
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
}
