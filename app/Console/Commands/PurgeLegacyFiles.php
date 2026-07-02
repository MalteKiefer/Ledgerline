<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove the pre-zero-knowledge file objects from object storage.
 *
 * The old per-row file model stored uploads under "files/"; after the vault
 * reset those objects are unreachable ciphertext/plaintext leftovers. This
 * deletes them. Blobs of the new model live under "vault/" and are untouched.
 */
class PurgeLegacyFiles extends Command
{
    protected $signature = 'vault:purge-legacy {--force : Delete without asking}';

    protected $description = 'Delete legacy pre-zero-knowledge file objects (files/ prefix) from the files disk';

    public function handle(): int
    {
        $disk = Storage::disk(config('files.disk'));
        $objects = $disk->files('files');

        if ($objects === []) {
            $this->info('No legacy objects found.');

            return self::SUCCESS;
        }

        $count = count($objects);
        if (! $this->option('force') && ! $this->confirm("Delete {$count} legacy object(s) under files/? This cannot be undone.")) {
            return self::FAILURE;
        }

        $disk->delete($objects);
        $this->info("Deleted {$count} legacy object(s).");

        return self::SUCCESS;
    }
}
