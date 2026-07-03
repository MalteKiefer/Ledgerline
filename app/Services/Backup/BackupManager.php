<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Services\Backup\Sources\BackupSource;
use App\Services\Backup\Sources\DatabaseSource;
use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\GallerySource;
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

    private const MIRROR_PREFIX = ['files' => 'files', 'gallery' => 'photos'];

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
            if ($job->destination === null) {
                throw new RuntimeException('No destination configured for this backup job.');
            }
            $step('Destination: '.$job->destination->name.' ('.$job->destination->driver.').');

            $prefix = (Str::slug($job->name) ?: 'backup').'-'.$job->id;
            $fs = $this->destinations->make($job->destination);

            // Files/Gallery can be either an incremental mirror (default) or a
            // full archive; the database is always a full archive.
            $useMirror = in_array($job->source, self::MIRROR_SOURCES, true) && ($job->mode ?? 'mirror') !== 'archive';

            if ($useMirror) {
                // Incremental mirror: upload only new objects, remove vanished
                // ones (no archive, no gzip, no local staging).
                $step('Mirroring '.$job->source.' to '.$prefix.'…');
                $r = $this->mirror->mirror($fs, self::MIRROR_PREFIX[$job->source], $prefix, $step, $checkCancel);
                $bytes = $r['bytes'];
                $filename = $prefix.'/'; // a folder mirror, not a single archive
                $summary = sprintf('%s → %s (%s, %d new, %d removed)', $job->source, $prefix, $this->human($bytes), $r['uploaded'], $r['removed']);
            } else {
                $step('Building '.$job->source.' archive…');
                $artifact = $this->source($job->source)->build($workDir);
                $uploadPath = $artifact->path;
                $extension = $artifact->extension;
                $step('Archive built: '.$this->human((int) (filesize($uploadPath) ?: 0)).'.');

                if ($job->encrypt) {
                    if (! $job->passphrase) {
                        throw new RuntimeException('Encryption is enabled but no passphrase is set.');
                    }
                    $step('Encrypting archive…');
                    $encPath = $artifact->path.'.enc';
                    $this->cipher->encryptFile($artifact->path, $encPath, (string) $job->passphrase);
                    @unlink($artifact->path);
                    $uploadPath = $encPath;
                    $extension .= '.enc';
                    $step('Encrypted: '.$this->human((int) (filesize($uploadPath) ?: 0)).'.');
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

                $summary = sprintf('%s → %s (%s)', $job->source, $filename, $this->human($bytes));
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

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, 1).' '.$units[$i];
    }
}
