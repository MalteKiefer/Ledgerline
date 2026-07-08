<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prune expired share artifacts.
 *
 * Note share links are gone (notes now live in the zero-knowledge store), so
 * there is nothing left to prune here. The command is kept as the scheduled
 * hook for any future expiring share type.
 */
class PruneShares extends Command
{
    protected $signature = 'shares:prune';

    protected $description = 'Delete expired share links';

    public function handle(): int
    {
        $this->info('Deleted 0 expired share(s).');

        return self::SUCCESS;
    }
}
