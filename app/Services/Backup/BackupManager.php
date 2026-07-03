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

        try {
            if ($job->destination === null) {
                throw new RuntimeException('No destination configured for this backup job.');
            }

            $artifact = $this->source($job->source)->build($workDir);
            $uploadPath = $artifact->path;
            $extension = $artifact->extension;

            if ($job->encrypt) {
                if (! $job->passphrase) {
                    throw new RuntimeException('Encryption is enabled but no passphrase is set.');
                }
                $encPath = $artifact->path.'.enc';
                $this->cipher->encryptFile($artifact->path, $encPath, (string) $job->passphrase);
                @unlink($artifact->path);
                $uploadPath = $encPath;
                $extension .= '.enc';
            }

            $prefix = Str::slug($job->name) ?: 'backup';
            $filename = $prefix.'/'.Carbon::now()->format('Y-m-d_His').'.'.$extension;

            $fs = $this->destinations->make($job->destination);
            $stream = fopen($uploadPath, 'rb');
            try {
                $fs->writeStream($filename, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $bytes = (int) (filesize($uploadPath) ?: 0);
            $this->prune($fs, $prefix, $job->retention);

            $run->update([
                'status' => 'success',
                'finished_at' => Carbon::now(),
                'bytes' => $bytes,
                'filename' => $filename,
            ]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'success']);
            $summary = sprintf('%s → %s (%s)', $job->source, $filename, $this->human($bytes));
            $this->notifier->notify($job, true, $summary);
            AppNotification::record('success', __('notifications.backup_ok', ['name' => $job->name]), $summary, 'backup');
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'finished_at' => Carbon::now(), 'message' => Str::limit($e->getMessage(), 1000)]);
            $job->update(['last_run_at' => Carbon::now(), 'last_status' => 'failed']);
            $this->notifier->notify($job, false, $e->getMessage());
            AppNotification::record('error', __('notifications.backup_failed', ['name' => $job->name]), Str::limit($e->getMessage(), 300), 'backup');
        } finally {
            File::deleteDirectory($workDir);
        }

        return $run->refresh();
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

    /** Keep only the newest $retention objects under the job's prefix. */
    private function prune(Filesystem $fs, string $prefix, int $retention): void
    {
        if ($retention < 1) {
            return;
        }
        $files = [];
        foreach ($fs->listContents($prefix, false) as $item) {
            if ($item->isFile()) {
                $files[] = $item->path();
            }
        }
        // Object names are timestamped, so a plain descending sort is newest-first.
        rsort($files);
        foreach (array_slice($files, $retention) as $old) {
            $fs->delete($old);
        }
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
