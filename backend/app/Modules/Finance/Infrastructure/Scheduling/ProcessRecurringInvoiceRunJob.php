<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Scheduling;

use App\Modules\Finance\Application\Commands\Recurring\ProcessRecurringInvoiceRun;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Processes one recurring run's next incomplete step. Unique per run UUID
 * so a scheduler sweep can safely re-dispatch a run that already has a job
 * in flight without ever racing it. Failures use Laravel's own retry/backoff;
 * a run left `failed` after the final attempt requires an explicit
 * `RetryRecurringInvoiceRun` before it is picked up again.
 */
final class ProcessRecurringInvoiceRunJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $ownerId,
        public readonly int $runId,
        public readonly string $runUuid,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200];
    }

    public function uniqueId(): string
    {
        return $this->runUuid;
    }

    public function handle(ProcessRecurringInvoiceRun $processor): void
    {
        // A real queue worker reuses one PHP process across many jobs for many
        // different owners, so the acting owner must never leak into the next
        // job. Restore whatever was authenticated before (nothing, in a worker;
        // possibly a test's own `actingAs`, when the sync queue driver runs this
        // inline) rather than unconditionally forgetting every guard.
        $previous = Auth::user();
        Auth::onceUsingId($this->ownerId);

        try {
            $processor->handle(new RecurringRunId($this->runId));
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
