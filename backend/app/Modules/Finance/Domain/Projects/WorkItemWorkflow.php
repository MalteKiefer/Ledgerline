<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Workflow\StateMachine;

final readonly class WorkItemWorkflow
{
    private StateMachine $transitions;

    public function __construct()
    {
        $this->transitions = new StateMachine([
            WorkItemStatus::Open->value => [
                WorkItemStatus::InProgress->value,
                WorkItemStatus::Done->value,
            ],
            WorkItemStatus::InProgress->value => [
                WorkItemStatus::Open->value,
                WorkItemStatus::Done->value,
            ],
            WorkItemStatus::Done->value => [WorkItemStatus::InProgress->value],
        ]);
    }

    public function can(WorkItemStatus|string $from, WorkItemStatus|string $to): bool
    {
        return $this->transitions->can(self::value($from), self::value($to));
    }

    public function assertCan(WorkItemStatus|string $from, WorkItemStatus|string $to): void
    {
        if (! $this->can($from, $to)) {
            throw new InvalidProjectAction('invalid_transition');
        }
    }

    public function assertEstimateAllowed(bool $milestone, ?DecimalQuantity $estimate): void
    {
        if ($milestone && $estimate !== null) {
            throw new InvalidProjectAction('milestone_estimate_not_allowed');
        }
    }

    public function assertSourceLineIndex(bool $quoteDerived, ?int $sourceLineIndex): void
    {
        if ($quoteDerived && ($sourceLineIndex === null || $sourceLineIndex < 0)) {
            throw new InvalidProjectAction('quote_source_line_invalid');
        }
    }

    private static function value(WorkItemStatus|string $status): string
    {
        return $status instanceof WorkItemStatus ? $status->value : $status;
    }
}
