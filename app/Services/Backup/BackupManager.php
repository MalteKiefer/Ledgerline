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

            // One RUN produces one timestamped BATCH folder holding one archive per
            // selected source; GFS retention then rotates whole batches.
            $batch = $prefix.'/'.Carbon::now()->format('Y-m-d_His');
            $passphrase = null;
            if ($job->encrypt) {
                $passphrase = $job->effectivePassphrase();
                if ($passphrase === null) {
                    throw new RuntimeException('Encryption is enabled but no passphrase is set.');
                }
            }
            $incremental = $job->mode === 'incremental';
            $sinceTs = $incremental ? $job->last_run_at?->getTimestamp() : null;

            $bytes = 0;
            $done = [];
            foreach ($job->effectiveSources() as $src) {
                $sourceObj = $this->source($src);
                // Incremental only narrows blob (disk-prefix) sources; the DB dump is
                // always a full snapshot.
                if ($sinceTs !== null && $sourceObj instanceof Sources\DiskArchiveSource && in_array($src, BackupJob::INCREMENTAL_SOURCES, true)) {
                    $sourceObj->onlySince($sinceTs);
                }
                $step('Building '.$src.($sinceTs !== null && in_array($src, BackupJob::INCREMENTAL_SOURCES, true) ? ' (incremental)' : '').' archive…');
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

                $filename = $batch.'/'.$src.'.'.$extension;
                $step('Uploading '.$filename.'…');
                $stream = fopen($uploadPath, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('Could not open the staged archive for upload.');
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
            $uploadedArchive = $batch; // the whole batch folder, removed on cancel
            $step('Upload complete: '.implode(', ', $done).'.');

            $deleted = $this->pruneGfs($fs, $prefix, $job);
            $step($deleted > 0
                ? sprintf('GFS retention: removed %d old batch(es).', $deleted)
                : 'GFS retention: nothing to remove.');

            $filename = $batch;
            $summary = sprintf('%s → %s (%s)', implode('+', $done), $batch, Bytes::format($bytes));

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
            default => throw new RuntimeException("Unknown backup source: {$source}"),
        };
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
     * Restore a blob source (files/invoices) from a batch: download the source
     * archive, decrypt if needed, extract it back onto the source disk (additive
     * overwrite). Returns the number of files written. DB restore is intentionally
     * NOT one-click (download the dump + run backup:restore-db).
     */
    public function restoreBlobs(BackupJob $job, string $batchPath, string $source): int
    {
        if (! in_array($source, ['files', 'invoices'], true)) {
            throw new RuntimeException('Only files/invoices can be restored in-place.');
        }
        if ($job->destination === null) {
            throw new RuntimeException('No destination.');
        }
        $fs = $this->destinations->make($job->destination);
        $remote = $this->archiveIn($fs, $batchPath, $source);
        if ($remote === null) {
            throw new RuntimeException('Archive not found in batch: '.$source);
        }
        $enc = str_ends_with($remote, '.enc');

        $work = storage_path('app/backup-tmp/'.Str::uuid()->toString());
        File::ensureDirectoryExists($work, 0700);
        $local = $work.'/'.$source.'.tar.gz'.($enc ? '.enc' : '');
        $in = $fs->readStream($remote);
        $out = fopen($local, 'wb');
        if (! is_resource($in) || ! is_resource($out)) {
            throw new RuntimeException('Could not stage the archive.');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($enc) {
            $pass = $job->effectivePassphrase();
            if ($pass === null) {
                throw new RuntimeException('Archive is encrypted but no passphrase is set.');
            }
            $dec = $work.'/'.$source.'.tar.gz';
            $this->cipher->decryptFile($local, $dec, $pass);
            @unlink($local);
            $local = $dec;
        }

        $disk = BlobStore::disk();
        $phar = new \PharData($local);
        $written = 0;
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            /** @var \PharFileInfo $file */
            $rel = ltrim(str_replace('phar://'.$local, '', $file->getPathname()), '/');
            $rel = preg_replace('#^[^/]+/#', '', $rel) ?? $rel; // drop the archive's top dir
            if ($rel === '' || str_contains($rel, '..')) {
                continue;
            }
            $disk->put($source.'/'.$rel, (string) file_get_contents($file->getPathname()));
            $written++;
        }
        File::deleteDirectory($work);

        return $written;
    }
}
