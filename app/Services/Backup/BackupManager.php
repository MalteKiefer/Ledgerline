<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Models\FileBlob;
use App\Models\GalleryBlob;
use App\Services\Backup\Sources\BackupSource;
use App\Services\Backup\Sources\DatabaseSource;
use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\GallerySource;
use App\Support\Bytes;
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
    /** Sources mirrored object-by-object (already-encrypted blobs), not archived. */
    private const MIRROR_SOURCES = ['files', 'gallery'];

    // Disk prefix per source = the blob controller's module() (where blobs are
    // actually written): files→'files', gallery→'gallery' (NOT the old 'photos').
    private const MIRROR_PREFIX = ['files' => 'files', 'gallery' => 'gallery'];

    /** Blob ownership ledger per mirror source — drives the incremental delta + byte total. */
    private const MIRROR_LEDGER = ['files' => FileBlob::class, 'gallery' => GalleryBlob::class];

    public function __construct(
        private readonly BackupDestinationFactory $destinations,
        private readonly ArchiveCipher $cipher,
        private readonly BackupNotifier $notifier,
        private readonly DiskMirror $mirror,
    ) {}

    public function run(BackupJob $job): BackupRun
    {
        $run = $job->runs()->create(['status' => 'running', 'started_at' => Carbon::now()]);
        $workDir = storage_path('app/backup-tmp/'.Str::uuid()->toString());
        File::ensureDirectoryExists($workDir, 0700);

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
        // cancelled run leaves nothing behind. (Mirror uploads clean themselves.)
        $uploadedArchive = null;
        $fs = null;

        try {
            $step(sprintf('Backup "%s" started (source: %s).', $job->name, $job->source));
            // A database dump carries every non-zero-knowledge module in plaintext
            // AND the wrapped vault-key material (an offline passphrase-cracking
            // oracle). It must never leave the box unencrypted — enforce here, not
            // only in the controller, so a job created via seeder/console/legacy
            // can't ship a cleartext dump.
            if ($job->source === 'database' && (! $job->encrypt || ! $job->effectivePassphrase())) {
                throw new RuntimeException('A database backup must be encrypted (set encryption + a passphrase).');
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

            // Files/Gallery can be either an incremental mirror (default) or a
            // full archive; the database is always a full archive.
            $useMirror = in_array($job->source, self::MIRROR_SOURCES, true) && ($job->mode ?? 'mirror') !== 'archive';

            if ($useMirror) {
                $diskPrefix = self::MIRROR_PREFIX[$job->source];
                $ledger = self::MIRROR_LEDGER[$job->source];
                // Total stored size comes straight from the blob ledger (one SQL
                // sum) instead of a size() HEAD per object — the metric no longer
                // costs tens of thousands of storage calls per run.
                $bytes = (int) $ledger::query()->sum('size');
                $filename = $prefix.'/'; // a folder mirror, not a single archive

                // Full list-and-prune reconcile only once per window; every other
                // run is a fast delta of just the blobs added since the cursor.
                $reconcileHours = max(0, (int) config('backup.reconcile_hours', 24));
                $needFull = $reconcileHours === 0
                    || $job->last_full_mirror_at === null
                    || $job->last_full_mirror_at->lt(Carbon::now()->subHours($reconcileHours));

                if ($needFull) {
                    $step('Full reconcile of '.$job->source.' → '.$prefix.'…');
                    $r = $this->mirror->mirror($fs, $diskPrefix, $prefix, $step, $checkCancel);
                    $cursor = $ledger::query()->max('created_at');
                    $job->forceFill([
                        'last_full_mirror_at' => Carbon::now(),
                        'mirror_cursor' => $cursor,
                    ])->save();
                    $summary = sprintf('%s → %s (%s, %d uploaded, %d removed, full reconcile)', $job->source, $prefix, Bytes::format($bytes), $r['uploaded'], $r['removed']);
                } else {
                    $step('Incremental mirror of '.$job->source.' → '.$prefix.'…');
                    $newBlobs = $ledger::query()
                        ->when($job->mirror_cursor !== null, fn ($q) => $q->where('created_at', '>', $job->mirror_cursor))
                        ->orderBy('created_at')
                        ->pluck('blob');
                    $r = $this->mirror->delta($fs, $diskPrefix, $prefix, $newBlobs, $step, $checkCancel);
                    // Advance the cursor to the newest blob we considered.
                    $cursor = $ledger::query()
                        ->when($job->mirror_cursor !== null, fn ($q) => $q->where('created_at', '>', $job->mirror_cursor))
                        ->max('created_at') ?? $job->mirror_cursor;
                    if ($cursor !== null) {
                        $job->forceFill(['mirror_cursor' => $cursor])->save();
                    }
                    $summary = sprintf('%s → %s (%s, %d new)', $job->source, $prefix, Bytes::format($bytes), $r['uploaded']);
                }
            } else {
                $step('Building '.$job->source.' archive…');
                $artifact = $this->source($job->source)->build($workDir);
                $uploadPath = $artifact->path;
                $extension = $artifact->extension;
                $step('Archive built: '.Bytes::format((int) (filesize($uploadPath) ?: 0)).'.');

                if ($job->encrypt) {
                    $passphrase = $job->effectivePassphrase();
                    if ($passphrase === null) {
                        throw new RuntimeException('Encryption is enabled but no passphrase is set.');
                    }
                    $step('Encrypting archive…');
                    $encPath = $artifact->path.'.enc';
                    $this->cipher->encryptFile($artifact->path, $encPath, $passphrase);
                    @unlink($artifact->path);
                    $uploadPath = $encPath;
                    $extension .= '.enc';
                    $step('Encrypted: '.Bytes::format((int) (filesize($uploadPath) ?: 0)).'.');
                }

                $filename = $prefix.'/'.Carbon::now()->format('Y-m-d_His').'.'.$extension;
                $step('Uploading to '.$filename.'…');
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
                $uploadedArchive = $filename;
                $step('Upload complete.');

                $bytes = (int) (filesize($uploadPath) ?: 0);
                $deleted = $this->prune($fs, $prefix, $job->retention);
                $step($deleted > 0
                    ? sprintf('Retention: kept %d, removed %d old version(s).', $job->retention, $deleted)
                    : sprintf('Retention: keeping up to %d version(s).', $job->retention));

                $summary = sprintf('%s → %s (%s)', $job->source, $filename, Bytes::format($bytes));
            }

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
            // Remove any complete archive already pushed this run. (Mirror runs
            // roll back their own partial uploads inside DiskMirror.)
            if ($uploadedArchive !== null && $fs !== null) {
                try {
                    $fs->delete($uploadedArchive);
                    $log[] = Carbon::now()->format('H:i:s').'  Removed uploaded archive.';
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
        $patterns = [
            '/(--password[=\s]+)\S+/i' => '$1***',
            '/(\s-p)\S+/' => '$1***',
            '/(password["\']?\s*[:=]\s*["\']?)[^"\'\s,&]+/i' => '$1***',
            '/([a-z][a-z0-9+.\-]*:\/\/[^:\/\s@]+:)[^@\/\s]+@/i' => '$1***@',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $message);
    }

    private function source(string $source): BackupSource
    {
        return match ($source) {
            'database' => app(DatabaseSource::class),
            'files' => app(FilesSource::class),
            'gallery' => app(GallerySource::class),
            default => throw new RuntimeException("Unknown backup source: {$source}"),
        };
    }

    /** Keep only the newest $retention objects under the job's prefix; returns how many were deleted. */
    private function prune(Filesystem $fs, string $prefix, int $retention): int
    {
        if ($retention < 1) {
            return 0;
        }
        $files = [];
        foreach ($fs->listContents($prefix, false) as $item) {
            if ($item->isFile()) {
                // Sort by the object's actual mtime (newest first), not by the
                // filename — robust even if the naming scheme changes.
                $files[] = ['path' => $item->path(), 'ts' => (int) $item->lastModified()];
            }
        }
        usort($files, fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
        $old = array_slice($files, $retention);
        foreach ($old as $f) {
            $fs->delete($f['path']);
        }

        return count($old);
    }
}
