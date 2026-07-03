<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\AppNotification;
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

        // Step-by-step log persisted to the run so the operator can see exactly
        // what happened. Flushed after each step so a crash still leaves a trail.
        $log = [];
        $step = function (string $msg) use (&$log, $run): void {
            $log[] = Carbon::now()->format('H:i:s').'  '.$msg;
            $run->update(['log' => implode("\n", $log)]);
        };

        try {
            $step(sprintf('Backup "%s" started (source: %s).', $job->name, $job->source));
            if ($job->destination === null) {
                throw new RuntimeException('No destination configured for this backup job.');
            }
            $step('Destination: '.$job->destination->name.' ('.$job->destination->driver.').');

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

            // Unique per job (append the id) so two jobs whose names slug to the
            // same value never share a directory — otherwise one job's retention
            // prune would delete the other's archives.
            $prefix = (Str::slug($job->name) ?: 'backup').'-'.$job->id;
            $filename = $prefix.'/'.Carbon::now()->format('Y-m-d_His').'.'.$extension;

            $step('Uploading to '.$filename.'…');
            $fs = $this->destinations->make($job->destination);
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
            $step('Upload complete.');

            $bytes = (int) (filesize($uploadPath) ?: 0);
            $deleted = $this->prune($fs, $prefix, $job->retention);
            $step($deleted > 0
                ? sprintf('Retention: kept %d, removed %d old version(s).', $job->retention, $deleted)
                : sprintf('Retention: keeping up to %d version(s).', $job->retention));

            $run->update([
                'status' => 'success',
                'finished_at' => Carbon::now(),
                'bytes' => $bytes,
                'filename' => $filename,
            ]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'success']);
            $summary = sprintf('%s → %s (%s)', $job->source, $filename, $this->human($bytes));
            $step('Done: '.$summary);
            $this->notifier->notify($job, true, $summary);
            AppNotification::record('success', __('notifications.backup_ok', ['name' => $job->name]), $summary, 'backup');
        } catch (\Throwable $e) {
            $detail = $this->describe($e);
            $step('FAILED: '.$detail);
            $run->update(['status' => 'failed', 'finished_at' => Carbon::now(), 'message' => Str::limit($detail, 1000)]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'failed']);
            $this->notifier->notify($job, false, $detail);
            AppNotification::record('error', __('notifications.backup_failed', ['name' => $job->name]), Str::limit($detail, 300), 'backup');
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
            $parts[] = class_basename($cur).': '.$cur->getMessage();
        }

        return implode(' ← ', array_unique($parts));
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
