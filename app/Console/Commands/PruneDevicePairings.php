<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\Pairing;
use Illuminate\Console\Command;

/**
 * Delete expired and spent QR device-pairing rows. Pairings are short-lived
 * (~2 min) and single-use; this keeps the table from accumulating dead rows.
 */
class PruneDevicePairings extends Command
{
    protected $signature = 'device-pairings:prune';

    protected $description = 'Delete expired and consumed device-pairing sessions';

    public function handle(Pairing $pairing): int
    {
        $deleted = $pairing->prune();
        $this->info("Pruned {$deleted} device pairing(s).");

        return self::SUCCESS;
    }
}
