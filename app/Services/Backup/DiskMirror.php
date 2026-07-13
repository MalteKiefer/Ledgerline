<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Support\BlobStore;
use League\Flysystem\Filesystem;

/**
 * Incrementally mirrors a prefix of the files disk to a backup destination.
 *
 * Stored file blobs are immutable (their name is content-addressed) and
 * already client-side encrypted, so there is nothing to archive or compress:
 * this uploads only objects the destination is missing and removes objects that
 * no longer exist at the source. Server-to-server streaming, no local staging.
 */
class DiskMirror
{
    /**
     * Full list-and-prune reconcile: upload objects the destination is missing
     * and delete destination objects the source no longer has. This scans both
     * the whole source prefix and the whole destination, so it is the expensive
     * path — the manager only runs it once per reconcile window; the fast
     * incremental delta() below handles the routine runs.
     *
     * @param  callable(string):void  $step  progress logger
     * @param  (callable():void)|null  $checkCancel  throws to abort mid-mirror
     * @return array{source:int, uploaded:int, removed:int}
     */
    public function mirror(Filesystem $dest, string $sourcePrefix, string $destPrefix, callable $step, ?callable $checkCancel = null): array
    {
        $disk = BlobStore::disk();

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
        // Objects this run wrote — removed again if the run is cancelled, so a
        // half-finished mirror leaves no orphaned blobs at the destination.
        $written = [];
        try {
            foreach ($sourceFiles as $key) {
                if (isset($existing[$key])) {
                    continue; // immutable blob already mirrored → skip (no size() HEAD)
                }
                $read = $disk->readStream($key);
                if ($read === null) {
                    continue;
                }
                $dest->writeStream($destPrefix.'/'.$key, $read);
                if (is_resource($read)) {
                    fclose($read);
                }
                $written[] = $destPrefix.'/'.$key;
                $uploaded++;
                if ($uploaded % 50 === 0) {
                    $step("Uploaded {$uploaded} new object(s)…");
                    if ($checkCancel) {
                        $checkCancel();
                    }
                }
            }
        } catch (BackupCancelled $e) {
            // Roll back this run's uploads. Don't call $step here — it re-checks
            // the cancel flag and would re-throw before the cleanup completes.
            foreach ($written as $path) {
                $dest->delete($path);
            }
            throw $e;
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

        return ['source' => count($sourceFiles), 'uploaded' => $uploaded, 'removed' => $removed];
    }

    /**
     * Incremental delta upload: given the blob ids added since the last run (from
     * the blob ledger, cheapest possible source of truth), upload just those —
     * no full source scan, no full destination listing, no per-object HEAD. Blobs
     * are immutable and content-addressed, so a blob that is new to the ledger is
     * guaranteed absent at the destination; we still tolerate a re-write (idempotent).
     *
     * @param  iterable<string>  $blobIds  ledger blob uuids created since the cursor
     * @param  callable(string):void  $step
     * @param  (callable():void)|null  $checkCancel
     * @return array{uploaded:int, missing:int}
     */
    public function delta(Filesystem $dest, string $sourcePrefix, string $destPrefix, iterable $blobIds, callable $step, ?callable $checkCancel = null): array
    {
        $disk = BlobStore::disk();
        $uploaded = 0;
        $missing = 0;
        $written = [];
        try {
            foreach ($blobIds as $blob) {
                $key = $sourcePrefix.'/'.$blob;
                // Ledgered but possibly already gone from disk (freed between runs):
                // readStream throws on a missing object, so treat that as missing —
                // nothing to upload; the periodic reconcile prunes the dest.
                try {
                    $read = $disk->readStream($key);
                } catch (\Throwable $e) {
                    $read = null;
                }
                if ($read === null) {
                    $missing++;

                    continue;
                }
                $dest->writeStream($destPrefix.'/'.$key, $read);
                if (is_resource($read)) {
                    fclose($read);
                }
                $written[] = $destPrefix.'/'.$key;
                $uploaded++;
                if ($uploaded % 50 === 0) {
                    $step("Uploaded {$uploaded} new object(s)…");
                    if ($checkCancel) {
                        $checkCancel();
                    }
                }
            }
        } catch (BackupCancelled $e) {
            foreach ($written as $path) {
                $dest->delete($path);
            }
            throw $e;
        }

        $step(sprintf('Delta complete: %d uploaded, %d already gone.', $uploaded, $missing));

        return ['uploaded' => $uploaded, 'missing' => $missing];
    }
}
