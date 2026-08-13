<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Services\Backup\Sources\BackupSource;
use App\Services\Backup\Sources\DatabaseSource;
use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\InvoiceBlobSource;
use App\Support\BlobStore;
use App\Support\Bytes;
use App\Support\Redactor;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use RuntimeException;

/**
 * Runs one backup job end to end: build the source archive, optionally encrypt
 * it, upload it to the destination, prune old versions to the retention limit,
 * record the run and notify. Never throws — every run is recorded as success or
 * failure and reported through the job's notification channel.
 */
final class BackupManager
{
    public function __construct(
        private readonly BackupDestinationFactory $destinations,
        private readonly ArchiveCipher $cipher,
        private readonly BackupNotifier $notifier,
    ) {}

    public function run(BackupJob $job): BackupRun
    {
        $run = $job->runs()->create(['status' => 'running', 'started_at' => Carbon::now()]);

        // Self-heal the work-dir leak: a run cleans its own dir in finally, but a
        // worker KILLED mid-job (OOM, container recreate) never reaches it, leaving a
        // multi-GB staging/archive dir behind. Prune sibling dirs older than 4h (a run
        // finishes within the 3h job timeout, so anything older is definitely dead)
        // before staging this run — otherwise they accumulate to hundreds of GB.
        $this->pruneStaleWorkDirs();

        $workDir = storage_path('app/backup-tmp/'.Str::uuid()->toString());
        File::ensureDirectoryExists($workDir, 0700);
        // Fail loud + actionable instead of a cryptic repeated mkdir warning when
        // the storage/app volume is owned by the wrong uid (e.g. after a base-image
        // change): the fix is to chown storage/app to the container's app user.
        if (! is_dir($workDir) || ! is_writable($workDir)) {
            throw new RuntimeException('Backup work directory is not writable: '.$workDir.'. Check ownership of storage/app (chown it to the app container user).');
        }

        // Stop at the next checkpoint if the operator requested cancellation
        // (a fresh read, since the flag is set from another process).
        $checkCancel = function () use ($run): void {
            if (BackupRun::whereKey($run->id)->value('cancel_requested')) {
                throw new BackupCancelled('Backup cancelled.');
            }
        };

        // Step-by-step log persisted to the run so the operator can see exactly
        // what happened. Flushed after each step so a crash still leaves a trail.
        // Each step is also a cancellation checkpoint.
        $log = [];
        $step = function (string $msg) use (&$log, $run, $checkCancel): void {
            $log[] = Carbon::now()->format('H:i:s').'  '.$msg;
            $run->update(['log' => implode("\n", $log)]);
            $checkCancel();
        };

        // Archive uploaded to the destination this run — removed on cancel so a
        // cancelled run leaves nothing behind.
        $uploadedArchive = null;
        $fs = null;

        try {
            $step(sprintf('Backup "%s" started (source: %s).', $job->name, $job->source));
            // Encryption for a database dump is recommended (cleartext financial PII)
            // but optional (plaintext pivot removed the vault-key oracle; a local
            // FDE server may back up unencrypted by choice). Still fail closed if
            // encryption was REQUESTED but no key is available — silently shipping a
            // cleartext dump when the user asked for encryption would be worse.
            if ($job->encrypt && ! $job->effectivePassphrase()) {
                throw new RuntimeException('Encryption is enabled for this backup but no passphrase is set (job or BACKUP_PASSPHRASE).');
            }
            if ($job->destination === null) {
                throw new RuntimeException('No destination configured for this backup job.');
            }
            $step('Destination: '.$job->destination->name.' ('.$job->destination->driver.').');

            $prefix = (Str::slug($job->name) ?: 'backup').'-'.$job->id;
            $fs = $this->destinations->make($job->destination);
            // Create the destination folder if the configured path does not exist
            // yet, so a fresh target does not fail the first backup.
            $this->destinations->ensureRoot($fs, $job->destination->driver, $job->destination->config ?? []);

            // Two destination areas under the job prefix:
            //   db/<ts>/database.sql.gz   — point-in-time DB dumps, GFS-rotated
            //   mirror/<src>/<key>        — a LIVING file mirror per blob source
            // Blob sources are MIRRORED (upload only new/changed objects, tracked in a
            // remote ledger), never tarred: memory-flat, delta-only after the first
            // sync, resumable if a worker dies, and — unlike an incremental-tar chain —
            // retention can never orphan them. A 38 GB gallery costs one full first
            // sync, then near-nothing per run. Only the DB dump is a rotated archive.
            $ts = Carbon::now()->format('Y-m-d_His');
            $dbBatch = null;
            // The fail-closed guard above already verified a passphrase exists when
            // encryption is on, so this is non-null in the encrypted case.
            $passphrase = $job->encrypt ? $job->effectivePassphrase() : null;

            $bytes = 0;
            $done = [];
            foreach ($job->effectiveSources() as $src) {
                $sourceObj = $this->source($src);

                if ($sourceObj instanceof Sources\DiskArchiveSource) {
                    $step('Mirroring '.$src.'…');
                    $bytes += $this->mirrorSource($fs, $prefix, $src, $sourceObj->diskPrefix(), $passphrase, $step);
                    $done[] = $src;

                    continue;
                }

                // Database: full point-in-time dump into its own timestamped batch.
                $step('Building '.$src.' dump…');
                $artifact = $sourceObj->build($workDir);
                $uploadPath = $artifact->path;
                $extension = $artifact->extension;

                if ($passphrase !== null) {
                    $encPath = $artifact->path.'.enc';
                    $this->cipher->encryptFile($artifact->path, $encPath, $passphrase);
                    @unlink($artifact->path);
                    $uploadPath = $encPath;
                    $extension .= '.enc';
                }

                $dbBatch = $prefix.'/db/'.$ts;
                $filename = $dbBatch.'/'.$src.'.'.$extension;
                $step('Uploading '.$filename.'…');
                $stream = fopen($uploadPath, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('Could not open the staged dump for upload.');
                }
                try {
                    $fs->writeStream($filename, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $bytes += (int) (filesize($uploadPath) ?: 0);
                @unlink($uploadPath);
                $done[] = $src;
            }
            // Only the DB dump of THIS run is removable on cancel — the mirror is a
            // living copy of valid backups and must never be torn down.
            $uploadedArchive = $dbBatch;
            $step('Backup complete: '.implode(', ', $done).'.');

            // Retention rotates the DB dump batches only (mirror has no snapshots).
            $deleted = $this->pruneGfs($fs, $prefix.'/db', $job);
            $step($deleted > 0
                ? sprintf('GFS retention: removed %d old DB dump(s).', $deleted)
                : 'GFS retention: nothing to remove.');

            // filename anchors DB restore/download; falls back to the mirror root so a
            // mirror-only run (no DB source) still counts as a completed, restorable run.
            $filename = $dbBatch ?? $prefix.'/mirror';
            $summary = sprintf('%s (%s)', implode('+', $done), Bytes::format($bytes));

            // Log the completion directly (not via $step) so a cancel requested
            // at the very end can't flip an already-finished run to cancelled.
            $log[] = Carbon::now()->format('H:i:s').'  Done: '.$summary;
            $run->update([
                'status' => 'success',
                'finished_at' => Carbon::now(),
                'bytes' => $bytes,
                'filename' => $filename,
                'log' => implode("\n", $log),
            ]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'success']);
            $this->notifier->notify($job, true, $summary);
        } catch (BackupCancelled $e) {
            // Remove the whole batch folder already pushed this run.
            if ($uploadedArchive !== null && $fs !== null) {
                try {
                    $fs->deleteDirectory($uploadedArchive);
                    $log[] = Carbon::now()->format('H:i:s').'  Removed uploaded batch.';
                } catch (\Throwable) { /* best effort */
                }
            }
            $log[] = Carbon::now()->format('H:i:s').'  Cancelled by request.';
            $run->update(['status' => 'cancelled', 'finished_at' => Carbon::now(), 'message' => 'Cancelled.', 'log' => implode("\n", $log)]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'cancelled']);
        } catch (\Throwable $e) {
            $detail = $this->describe($e);
            $log[] = Carbon::now()->format('H:i:s').'  FAILED: '.$detail;
            $run->update(['status' => 'failed', 'finished_at' => Carbon::now(), 'message' => Str::limit($detail, 1000), 'log' => implode("\n", $log)]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'failed']);
            $this->notifier->notify($job, false, Str::limit($detail, 300));
        } finally {
            File::deleteDirectory($workDir);
        }

        return $run->refresh();
    }

    /**
     * Delete backup-tmp/<uuid> work dirs left behind by runs whose worker died
     * mid-job (the finally cleanup never ran). Anything older than 4h is orphaned —
     * a live run finishes within the 3h job timeout. Best-effort.
     */
    private function pruneStaleWorkDirs(): void
    {
        $base = storage_path('app/backup-tmp');
        if (! is_dir($base)) {
            return;
        }
        $cutoff = Carbon::now()->subHours(4)->getTimestamp();
        foreach (File::directories($base) as $dir) {
            if (! is_string($dir)) {
                continue;
            }
            $mtime = @filemtime($dir);
            if ($mtime !== false && $mtime < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }

    /** Full exception chain as a readable one-liner (root cause included). */
    private function describe(\Throwable $e): string
    {
        $parts = [];
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            $parts[] = class_basename($cur).': '.$this->redact($cur->getMessage());
        }

        return implode(' ← ', array_unique($parts));
    }

    /**
     * Strip credentials a dumper/driver may have echoed into its error (e.g. a
     * mysqldump command line or a connection URI), since this detail is stored
     * on the run and shown in the UI.
     */
    private function redact(string $message): string
    {
        return Redactor::redact($message);
    }

    private function source(string $source): BackupSource
    {
        return match ($source) {
            'database' => app(DatabaseSource::class),
            'invoices' => app(InvoiceBlobSource::class),
            'files' => app(FilesSource::class),
            'gallery' => app(Sources\GallerySource::class),
            'mail' => app(Sources\MailSource::class),
            'notes' => app(Sources\NotesSource::class),
            'avatars' => app(Sources\AvatarSource::class),
            default => throw new RuntimeException("Unknown backup source: {$source}"),
        };
    }

    /**
     * Mirror every object of a blob source to <prefix>/mirror/<key>, uploading only
     * files that are new or changed since the last sync (tracked in a remote
     * .ledger-<src>.json). Memory-flat (streams one object at a time), resumable (a
     * killed run re-syncs only the not-yet-uploaded files next run), and free of any
     * archive or delta chain — so a huge gallery never OOMs a worker and retention
     * can never orphan it. Returns the bytes uploaded THIS run (the delta).
     *
     * The object key already carries its source prefix (e.g. "gallery/uuid/orig"),
     * so all sources share one mirror/ tree and namespacing is automatic. Deleted
     * local files are intentionally NOT removed from the mirror (a backup keeps
     * copies; a local glitch must never wipe the offsite copy).
     */
    private function mirrorSource(Filesystem $fs, string $prefix, string $src, string $diskPrefix, ?string $passphrase, callable $step): int
    {
        $disk = BlobStore::disk();
        $ledgerPath = $prefix.'/mirror/.ledger-'.$src.'.json';
        $ledger = $this->readLedger($fs, $ledgerPath);
        $enc = $passphrase !== null;

        $uploaded = 0;
        $changed = 0;
        $skipped = 0;
        foreach ($disk->allFiles($diskPrefix) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $size = (int) $disk->size($key);
            $prev = $ledger[$key] ?? null;
            if (is_array($prev) && (int) ($prev['size'] ?? -1) === $size && (bool) ($prev['enc'] ?? false) === $enc) {
                $skipped++;

                continue; // unchanged since the last sync
            }

            $remote = $prefix.'/mirror/'.$key;
            if ($enc) {
                $tmp = storage_path('app/backup-tmp/mirror-'.Str::uuid()->toString());
                File::ensureDirectoryExists(dirname($tmp), 0700);
                $plain = $this->stageLocal($disk, $key, $tmp.'.plain');
                try {
                    $this->cipher->encryptFile($plain, $tmp, (string) $passphrase);
                    $this->streamUp($fs, $remote, $tmp);
                    $uploaded += (int) (@filesize($tmp) ?: 0);
                } finally {
                    @unlink($plain);
                    @unlink($tmp);
                }
            } else {
                $in = $disk->readStream($key);
                if (! is_resource($in)) {
                    throw new RuntimeException('Could not read '.$key.' for mirror.');
                }
                try {
                    $fs->writeStream($remote, $in);
                } finally {
                    if (is_resource($in)) {
                        fclose($in);
                    }
                }
                $uploaded += $size;
            }

            $ledger[$key] = ['size' => $size, 'enc' => $enc];
            $changed++;
            // Heartbeat (also a cancel checkpoint): persist the ledger before the
            // possible cancel throw so a resume never re-uploads what's already up.
            if ($changed % 250 === 0) {
                $this->writeLedger($fs, $ledgerPath, $ledger);
                $step(sprintf('Mirroring %s: %d uploaded (%s), %d unchanged…', $src, $changed, Bytes::format($uploaded), $skipped));
            }
        }
        $this->writeLedger($fs, $ledgerPath, $ledger);
        $step(sprintf('Mirror %s: %d uploaded (%s), %d unchanged.', $src, $changed, Bytes::format($uploaded), $skipped));

        return $uploaded;
    }

    /** Stream a disk object to a local path (so the cipher, which works on paths, can encrypt it). */
    private function stageLocal(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $key, string $dst): string
    {
        $in = $disk->readStream($key);
        $out = fopen($dst, 'wb');
        if (! is_resource($in) || ! is_resource($out)) {
            throw new RuntimeException('Could not stage '.$key.'.');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return $dst;
    }

    /** Stream a local file up to the destination. */
    private function streamUp(Filesystem $fs, string $remote, string $local): void
    {
        $s = fopen($local, 'rb');
        if ($s === false) {
            throw new RuntimeException('Could not open '.$local.' for upload.');
        }
        try {
            $fs->writeStream($remote, $s);
        } finally {
            if (is_resource($s)) {
                fclose($s);
            }
        }
    }

    /**
     * Read a source's mirror ledger (key → {size, enc}); empty/corrupt is treated as
     * "nothing mirrored yet" so the next run re-syncs everything (safe, idempotent).
     *
     * @return array<string, array{size: int, enc: bool}>
     */
    private function readLedger(Filesystem $fs, string $path): array
    {
        if (! $fs->fileExists($path)) {
            return [];
        }
        try {
            $raw = $fs->read($path);
        } catch (\Throwable) {
            return [];
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && is_array($v) && isset($v['size']) && is_numeric($v['size'])) {
                $out[$k] = ['size' => (int) $v['size'], 'enc' => (bool) ($v['enc'] ?? false)];
            }
        }

        return $out;
    }

    /** @param  array<string, array{size: int, enc: bool}>  $ledger */
    private function writeLedger(Filesystem $fs, string $path, array $ledger): void
    {
        $fs->write($path, (string) json_encode($ledger));
    }

    /**
     * Grandfather-father-son retention over the timestamped batch folders: keep
     * the newest `daily` batches (son), plus one per distinct ISO week for
     * `weekly` weeks (father), plus one per distinct month for `monthly` months
     * (grandfather). A batch survives if any tier keeps it. Returns #deleted.
     */
    private function pruneGfs(Filesystem $fs, string $prefix, BackupJob $job): int
    {
        $tiers = $job->retentionTiers();
        $batches = [];
        foreach ($fs->listContents($prefix, false) as $item) {
            if (method_exists($item, 'isDir') ? $item->isDir() : ! $item->isFile()) {
                $batches[] = ['path' => $item->path(), 'ts' => (int) $item->lastModified()];
            }
        }
        if ($batches === []) {
            return 0;
        }
        usort($batches, fn (array $a, array $b): int => $b['ts'] <=> $a['ts']); // newest first

        $keep = [];
        // Son: newest N batches.
        foreach (array_slice($batches, 0, $tiers['daily']) as $b) {
            $keep[$b['path']] = true;
        }
        // Father: newest batch per ISO year-week, up to N weeks.
        $keep += $this->keepPerPeriod($batches, 'oW', $tiers['weekly']);
        // Grandfather: newest batch per month, up to N months.
        $keep += $this->keepPerPeriod($batches, 'Ym', $tiers['monthly']);

        $deleted = 0;
        foreach ($batches as $b) {
            if (! isset($keep[$b['path']])) {
                $fs->deleteDirectory($b['path']);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param  list<array{path: string, ts: int}>  $batches  newest-first
     * @return array<string, bool>
     */
    private function keepPerPeriod(array $batches, string $fmt, int $count): array
    {
        if ($count < 1) {
            return [];
        }
        $keep = [];
        $seen = [];
        foreach ($batches as $b) {
            $period = date($fmt, $b['ts']);
            if (isset($seen[$period])) {
                continue; // already kept the newest batch for this period
            }
            if (count($seen) >= $count) {
                break;
            }
            $seen[$period] = true;
            $keep[$b['path']] = true;
        }

        return $keep;
    }

    /**
     * Resolve the stored archive path for one source inside a batch folder,
     * trying the known extensions (encrypted variant first). Returns null if the
     * batch has no archive for that source.
     */
    public function archiveIn(Filesystem $fs, string $batch, string $source): ?string
    {
        $exts = match ($source) {
            'database' => ['sql.gz', 'sqlite.gz'],
            default => ['tar.gz'],
        };
        foreach ($exts as $ext) {
            foreach (['.enc', ''] as $suffix) {
                $path = $batch.'/'.$source.'.'.$ext.$suffix;
                if ($fs->fileExists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Restore a blob source from its MIRROR back onto the live files disk (additive
     * overwrite). Streams each mirrored object one at a time (memory-flat), decrypting
     * per the ledger's enc flag. Returns the number of files written. DB restore is
     * intentionally NOT one-click (download the dump + run backup:restore-db).
     *
     * $batchPath is accepted for signature compatibility but unused — the mirror lives
     * at a stable location derived from the job, not inside a timestamped batch.
     */
    public function restoreBlobs(BackupJob $job, string $batchPath, string $source): int
    {
        if ($source === 'database') {
            throw new RuntimeException('Database restore is not one-click; use backup:restore-db.');
        }
        if (! in_array($source, ['files', 'invoices', 'gallery', 'mail', 'notes', 'avatars'], true)) {
            throw new RuntimeException('Unknown blob source: '.$source);
        }
        if ($job->destination === null) {
            throw new RuntimeException('No destination.');
        }
        $fs = $this->destinations->make($job->destination);
        $prefix = (Str::slug($job->name) ?: 'backup').'-'.$job->id;
        $mirrorRoot = $prefix.'/mirror/';
        $ledger = $this->readLedger($fs, $prefix.'/mirror/.ledger-'.$source.'.json');
        $disk = BlobStore::disk();
        $pass = $job->effectivePassphrase();

        $written = 0;
        // The stored key already carries its source prefix (e.g. "gallery/uuid/orig"),
        // so it maps straight back onto the disk.
        foreach ($fs->listContents($mirrorRoot.$source, true) as $item) {
            if (method_exists($item, 'isDir') ? $item->isDir() : ! $item->isFile()) {
                continue;
            }
            $remote = $item->path();
            $key = substr($remote, strlen($mirrorRoot));
            if ($key === '' || str_contains($key, '..') || str_ends_with($key, '.json')) {
                continue;
            }
            $enc = (bool) ($ledger[$key]['enc'] ?? false);

            if ($enc) {
                if ($pass === null) {
                    throw new RuntimeException('Mirror is encrypted but no passphrase is set.');
                }
                // RAII: staged ciphertext + decrypted plaintext are shredded on every
                // exit path so no cleartext PII lingers in storage/app/backup-tmp.
                $work = storage_path('app/backup-tmp/restore-'.Str::uuid()->toString());
                File::ensureDirectoryExists(dirname($work), 0700);
                try {
                    $in = $fs->readStream($remote);
                    $out = fopen($work.'.enc', 'wb');
                    if (! is_resource($in) || ! is_resource($out)) {
                        throw new RuntimeException('Could not stage '.$key.'.');
                    }
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);
                    $this->cipher->decryptFile($work.'.enc', $work, $pass);
                    $plain = fopen($work, 'rb');
                    if (! is_resource($plain)) {
                        throw new RuntimeException('Could not open decrypted '.$key.'.');
                    }
                    try {
                        $disk->writeStream($key, $plain);
                    } finally {
                        fclose($plain);
                    }
                } finally {
                    @unlink($work.'.enc');
                    @unlink($work);
                }
            } else {
                $in = $fs->readStream($remote);
                if (! is_resource($in)) {
                    continue;
                }
                try {
                    $disk->writeStream($key, $in);
                } finally {
                    fclose($in);
                }
            }
            $written++;
        }

        return $written;
    }
}
