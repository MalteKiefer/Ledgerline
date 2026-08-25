<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Deadlines\DeadlineScanner;
use Illuminate\Console\Command;

/**
 * Reads the deadlines out of every user's already-extracted document text.
 *
 * Runs nightly rather than on write: the text arrives asynchronously (OCR and
 * indexing are worker jobs), so scanning on upload would read an empty column
 * half the time. Nothing here extracts text — it only reads what the modules
 * already indexed for search.
 */
class ScanDeadlines extends Command
{
    protected $signature = 'deadlines:scan {--user= : Only this user id}';

    protected $description = 'Find contract ends, notice periods and expiry dates in indexed document text';

    public function handle(DeadlineScanner $scanner): int
    {
        $only = $this->option('user');
        $onlyId = is_numeric($only) ? (int) $only : null;
        $users = User::query()->when($onlyId !== null, fn ($q) => $q->whereKey($onlyId))->get(['id']);

        $total = ['scanned' => 0, 'found' => 0, 'new' => 0];
        foreach ($users as $user) {
            $stats = $scanner->scanUser($user->id);
            foreach ($stats as $key => $value) {
                $total[$key] += $value;
            }
        }

        $this->info(sprintf('%d document(s) read, %d deadline(s) found, %d new.', $total['scanned'], $total['found'], $total['new']));

        return self::SUCCESS;
    }
}
