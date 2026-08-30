<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Scheduling;

use App\Modules\Finance\Application\Commands\Recurring\ClaimDueRecurringInvoiceRuns;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * One bounded scheduler tick: claim every due recurring occurrence (capped
 * globally and per template so one huge backlog cannot starve every other
 * owner) and dispatch processing for it, then sweep every run still in
 * flight (pending, mid-step after a crash, or waiting on an async mail
 * outcome) so it keeps making progress even if its own dispatch was lost.
 * Dispatch happens only after the claiming transaction commits, so a crash
 * between commit and dispatch leaves a `pending` run for the sweep to pick
 * up on the very next tick instead of silently losing it.
 */
final class RunRecurringInvoices extends Command
{
    protected $signature = 'finance:run-recurring-invoices';

    protected $description = 'Claim due recurring invoice occurrences and advance every in-flight run';

    private const int SWEEP_LIMIT = 1_000;

    public function handle(
        ClaimDueRecurringInvoiceRuns $claim,
        RecurringInvoiceRepository $templates,
        Clock $clock,
        LoggerInterface $logger,
    ): int {
        $claimed = $claim->handle($clock->now());
        $dispatched = [];
        foreach ($claimed as $run) {
            $dispatched[$run['run_id']] = true;
            $this->dispatchSafely($run, $logger);
        }

        foreach ($templates->inFlightRuns(self::SWEEP_LIMIT) as $run) {
            if (isset($dispatched[$run['run_id']])) {
                continue;
            }
            $dispatched[$run['run_id']] = true;
            $this->dispatchSafely($run, $logger);
        }

        $this->info(sprintf(
            'Claimed %d due recurring occurrence(s); swept %d additional in-flight run(s).',
            count($claimed),
            count($dispatched) - count($claimed),
        ));

        return self::SUCCESS;
    }

    /**
     * Dispatching never throws with a real (async) queue connection — this
     * guard exists so the `sync` connection (used in tests, and available as
     * an operator override) can never let one run's failure abort the rest
     * of the tick. The run's own failure is already durably recorded by
     * `ProcessRecurringInvoiceRun` before it rethrows; this only stops that
     * rethrow from reaching the scheduler loop.
     *
     * @param  array{run_id: int, owner_id: int, uuid: string}  $run
     */
    private function dispatchSafely(array $run, LoggerInterface $logger): void
    {
        try {
            ProcessRecurringInvoiceRunJob::dispatch($run['owner_id'], $run['run_id'], $run['uuid']);
        } catch (Throwable $exception) {
            $logger->error('Recurring invoice run processing failed synchronously.', [
                'exception' => $exception,
                'run_id' => $run['run_id'],
                'owner_id' => $run['owner_id'],
            ]);
        }
    }
}
