<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

/**
 * Incrementally mirrors a prefix of the files disk to a backup destination.
 *
 * The vault's content blobs are immutable (their name is content-addressed) and
 * already client-side encrypted, so there is nothing to archive or compress:
 * this uploads only objects the destination is missing and removes objects that
 * no longer exist at the source. Server-to-server streaming, no local staging.
 */
class DiskMirror
{
    /**
     * @param  callable(string):void  $step  progress logger
     * @return array{source:int, uploaded:int, removed:int, bytes:int}
     */
    public function mirror(Filesystem $dest, string $sourcePrefix, string $destPrefix, callable $step): array
    {
        $disk = Storage::disk(config('files.disk'));

        $step('Listing source objects…');
        $sourceFiles = $disk->allFiles($sourcePrefix); // keys relative to the disk root
        $sourceSet = array_fill_keys($sourceFiles, true);
        $step('Source has '.count($sourceFiles).' object(s).');

        // Existing destination objects (keys relative to the mirror folder).
        $existing = [];
        foreach ($dest->listContents($destPrefix, true) as $item) {
            if ($item->isFile()) {
                $existing[substr($item->path(), strlen($destPrefix) + 1)] = true;
            }
        }

        $uploaded = 0;
        $bytes = 0;
        foreach ($sourceFiles as $key) {
            $bytes += (int) $disk->size($key);
            if (isset($existing[$key])) {
                continue; // immutable blob already mirrored → skip
            }
            $read = $disk->readStream($key);
            if ($read === null) {
                continue;
            }
            $dest->writeStream($destPrefix.'/'.$key, $read);
            if (is_resource($read)) {
                fclose($read);
            }
            $uploaded++;
            if ($uploaded % 50 === 0) {
                $step("Uploaded {$uploaded} new object(s)…");
            }
        }

        // Remove destination objects whose source is gone.
        $removed = 0;
        foreach (array_keys($existing) as $key) {
            if (! isset($sourceSet[$key])) {
                $dest->delete($destPrefix.'/'.$key);
                $removed++;
            }
        }

        $step(sprintf('Mirror complete: %d object(s), %d uploaded, %d removed.', count($sourceFiles), $uploaded, $removed));

        return ['source' => count($sourceFiles), 'uploaded' => $uploaded, 'removed' => $removed, 'bytes' => $bytes];
    }
}
