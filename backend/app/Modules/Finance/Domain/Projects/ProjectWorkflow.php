<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Shared\Workflow\StateMachine;

final readonly class ProjectWorkflow
{
    private StateMachine $transitions;

    private StateMachine $reopenTransitions;

    public function __construct()
    {
        $this->transitions = new StateMachine([
            ProjectStatus::Planned->value => [
                ProjectStatus::Active->value,
                ProjectStatus::Cancelled->value,
            ],
            ProjectStatus::Active->value => [
                ProjectStatus::OnHold->value,
                ProjectStatus::Done->value,
                ProjectStatus::Cancelled->value,
            ],
            ProjectStatus::OnHold->value => [
                ProjectStatus::Active->value,
                ProjectStatus::Cancelled->value,
            ],
            ProjectStatus::Done->value => [],
            ProjectStatus::Cancelled->value => [],
        ]);
        $this->reopenTransitions = new StateMachine([
            ProjectStatus::Done->value => [ProjectStatus::Active->value],
            ProjectStatus::Cancelled->value => [ProjectStatus::Planned->value],
        ]);
    }

    public function can(ProjectStatus|string $from, ProjectStatus|string $to): bool
    {
        return $this->transitions->can(self::value($from), self::value($to));
    }

    public function assertCan(ProjectStatus|string $from, ProjectStatus|string $to): void
    {
        if (! $this->can($from, $to)) {
            throw new InvalidProjectAction('invalid_transition');
        }
    }

    public function canReopen(ProjectStatus|string $from, ProjectStatus|string $to): bool
    {
        return $this->reopenTransitions->can(self::value($from), self::value($to));
    }

    public function assertCanReopen(ProjectStatus|string $from, ProjectStatus|string $to): void
    {
        if (! $this->canReopen($from, $to)) {
            throw new InvalidProjectAction('invalid_transition');
        }
    }

    public function assertNotArchived(bool $archived): void
    {
        if ($archived) {
            throw new InvalidProjectAction('project_archived');
        }
    }

    public function assertParentAllowed(bool $createsCycle, bool $parentArchived): void
    {
        if ($createsCycle) {
            throw new InvalidProjectAction('project_parent_cycle');
        }

        if ($parentArchived) {
            throw new InvalidProjectAction('project_parent_archived');
        }
    }

    private static function value(ProjectStatus|string $status): string
    {
        return $status instanceof ProjectStatus ? $status->value : $status;
    }
}
