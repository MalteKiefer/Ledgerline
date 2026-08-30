<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Infrastructure\Compatibility\InvoiceCutoverCheck;
use Illuminate\Console\Command;

/**
 * Hard cutover gate for Task 17: exits non-zero if any owner requiring
 * migration has an incomplete checkpoint or a control-total mismatch.
 */
final class CheckFinanceInvoiceCutover extends Command
{
    protected $signature = 'finance:check-invoice-cutover';

    protected $description = 'Verify every owner has a complete, control-total-matching invoice migration';

    public function handle(InvoiceCutoverCheck $check): int
    {
        $result = $check->run();

        foreach ($result['owners'] as $owner) {
            $line = "owner {$owner['user_id']}: checkpoint={$owner['checkpoint_status']} controls=".($owner['controls_ok'] ? 'ok' : 'mismatch');
            if ($owner['checkpoint_status'] === 'complete' && $owner['controls_ok']) {
                $this->line($line);

                continue;
            }
            $this->error($line);
            foreach ($owner['mismatches'] as $mismatch) {
                $this->line('  - '.$mismatch);
            }
        }

        if (! $result['ready']) {
            $this->error('Cutover gate: NOT READY.');

            return self::FAILURE;
        }

        $this->info('Cutover gate: ready ('.count($result['owners']).' owner(s) verified).');

        return self::SUCCESS;
    }
}
