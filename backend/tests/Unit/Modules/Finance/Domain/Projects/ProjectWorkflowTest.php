<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Projects;

use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectBudget;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;
use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use App\Modules\Finance\Domain\Projects\WorkItemWorkflow;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectWorkflowTest extends TestCase
{
    #[DataProvider('ordinaryTransitions')]
    public function test_it_allows_every_ordinary_project_transition(ProjectStatus $from, ProjectStatus $to): void
    {
        $workflow = new ProjectWorkflow;

        $this->assertTrue($workflow->can($from, $to));
        $workflow->assertCan($from, $to);
    }

    /** @return iterable<string, array{ProjectStatus, ProjectStatus}> */
    public static function ordinaryTransitions(): iterable
    {
        yield 'planned to active' => [ProjectStatus::Planned, ProjectStatus::Active];
        yield 'planned to cancelled' => [ProjectStatus::Planned, ProjectStatus::Cancelled];
        yield 'active to on hold' => [ProjectStatus::Active, ProjectStatus::OnHold];
        yield 'active to done' => [ProjectStatus::Active, ProjectStatus::Done];
        yield 'active to cancelled' => [ProjectStatus::Active, ProjectStatus::Cancelled];
        yield 'on hold to active' => [ProjectStatus::OnHold, ProjectStatus::Active];
        yield 'on hold to cancelled' => [ProjectStatus::OnHold, ProjectStatus::Cancelled];
    }

    #[DataProvider('reopenTransitions')]
    public function test_terminal_projects_require_an_explicit_reopen_action(ProjectStatus $from, ProjectStatus $to): void
    {
        $workflow = new ProjectWorkflow;

        $this->assertFalse($workflow->can($from, $to));
        $this->assertTrue($workflow->canReopen($from, $to));
        $workflow->assertCanReopen($from, $to);

        $this->assertProjectError('invalid_transition', static fn (): null => $workflow->assertCan($from, $to));
    }

    /** @return iterable<string, array{ProjectStatus, ProjectStatus}> */
    public static function reopenTransitions(): iterable
    {
        yield 'done to active' => [ProjectStatus::Done, ProjectStatus::Active];
        yield 'cancelled to planned' => [ProjectStatus::Cancelled, ProjectStatus::Planned];
    }

    #[DataProvider('rejectedTransitions')]
    public function test_it_rejects_self_reverse_unknown_and_direct_terminal_jumps(ProjectStatus|string $from, ProjectStatus|string $to): void
    {
        $workflow = new ProjectWorkflow;

        $this->assertFalse($workflow->can($from, $to));
        $this->assertFalse($workflow->canReopen($from, $to));
        $this->assertProjectError('invalid_transition', static fn (): null => $workflow->assertCan($from, $to));
    }

    /** @return iterable<string, array{ProjectStatus|string, ProjectStatus|string}> */
    public static function rejectedTransitions(): iterable
    {
        yield 'self transition' => [ProjectStatus::Active, ProjectStatus::Active];
        yield 'reverse transition' => [ProjectStatus::Active, ProjectStatus::Planned];
        yield 'planned directly to done' => [ProjectStatus::Planned, ProjectStatus::Done];
        yield 'planned directly to on hold' => [ProjectStatus::Planned, ProjectStatus::OnHold];
        yield 'unknown source' => ['unknown', ProjectStatus::Active];
        yield 'unknown target' => [ProjectStatus::Active, 'unknown'];
    }

    public function test_archived_projects_reject_mutating_actions_with_a_stable_error_code(): void
    {
        $workflow = new ProjectWorkflow;

        $workflow->assertNotArchived(false);
        $this->assertProjectError('project_archived', static fn (): null => $workflow->assertNotArchived(true));
    }

    public function test_parent_cycles_and_archived_parents_are_rejected_with_stable_error_codes(): void
    {
        $workflow = new ProjectWorkflow;

        $workflow->assertParentAllowed(false, false);
        $this->assertProjectError(
            'project_parent_cycle',
            static fn (): null => $workflow->assertParentAllowed(true, false),
        );
        $this->assertProjectError(
            'project_parent_archived',
            static fn (): null => $workflow->assertParentAllowed(false, true),
        );
    }

    public function test_project_enums_expose_only_the_locked_wire_values(): void
    {
        $this->assertSame(
            ['planned', 'active', 'on_hold', 'done', 'cancelled'],
            array_column(ProjectStatus::cases(), 'value'),
        );
        $this->assertSame(['business', 'private'], array_column(ProjectKind::cases(), 'value'));
    }

    public function test_project_budget_preserves_optional_exact_minor_units_and_currency(): void
    {
        $budget = ProjectBudget::fromMinor(-12_345, 'eur');
        $unsetBudget = ProjectBudget::fromMinor(null, 'usd');

        $this->assertSame(-12_345, $budget->minor());
        $this->assertSame('EUR', $budget->currency());
        $this->assertNull($unsetBudget->minor());
        $this->assertSame('USD', $unsetBudget->currency());
    }

    #[DataProvider('workItemTransitions')]
    public function test_it_allows_every_work_item_transition(WorkItemStatus $from, WorkItemStatus $to): void
    {
        $workflow = new WorkItemWorkflow;

        $this->assertTrue($workflow->can($from, $to));
        $workflow->assertCan($from, $to);
    }

    /** @return iterable<string, array{WorkItemStatus, WorkItemStatus}> */
    public static function workItemTransitions(): iterable
    {
        yield 'open to in progress' => [WorkItemStatus::Open, WorkItemStatus::InProgress];
        yield 'open to done' => [WorkItemStatus::Open, WorkItemStatus::Done];
        yield 'in progress to open' => [WorkItemStatus::InProgress, WorkItemStatus::Open];
        yield 'in progress to done' => [WorkItemStatus::InProgress, WorkItemStatus::Done];
        yield 'done to in progress' => [WorkItemStatus::Done, WorkItemStatus::InProgress];
    }

    #[DataProvider('rejectedWorkItemTransitions')]
    public function test_it_rejects_self_and_unknown_work_item_transitions(WorkItemStatus|string $from, WorkItemStatus|string $to): void
    {
        $workflow = new WorkItemWorkflow;

        $this->assertFalse($workflow->can($from, $to));
        $this->assertProjectError('invalid_transition', static fn (): null => $workflow->assertCan($from, $to));
    }

    /** @return iterable<string, array{WorkItemStatus|string, WorkItemStatus|string}> */
    public static function rejectedWorkItemTransitions(): iterable
    {
        yield 'open to open' => [WorkItemStatus::Open, WorkItemStatus::Open];
        yield 'done to open' => [WorkItemStatus::Done, WorkItemStatus::Open];
        yield 'unknown source' => ['unknown', WorkItemStatus::Open];
        yield 'unknown target' => [WorkItemStatus::Open, 'unknown'];
    }

    public function test_milestones_forbid_estimated_quantities(): void
    {
        $workflow = new WorkItemWorkflow;

        $workflow->assertEstimateAllowed(true, null);
        $workflow->assertEstimateAllowed(false, DecimalQuantity::fromString('2.5000'));
        $this->assertProjectError(
            'milestone_estimate_not_allowed',
            static fn (): null => $workflow->assertEstimateAllowed(true, DecimalQuantity::fromString('1')),
        );
    }

    public function test_quote_derived_tasks_require_a_non_negative_zero_based_source_line_index(): void
    {
        $workflow = new WorkItemWorkflow;

        $workflow->assertSourceLineIndex(false, null);
        $workflow->assertSourceLineIndex(true, 0);
        $workflow->assertSourceLineIndex(true, 7);
        $this->assertProjectError(
            'quote_source_line_invalid',
            static fn (): null => $workflow->assertSourceLineIndex(true, null),
        );
        $this->assertProjectError(
            'quote_source_line_invalid',
            static fn (): null => $workflow->assertSourceLineIndex(true, -1),
        );
    }

    /** @param callable(): null $action */
    private function assertProjectError(string $errorCode, callable $action): void
    {
        try {
            $action();
            $this->fail(sprintf('Expected project error "%s".', $errorCode));
        } catch (InvalidProjectAction $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
        }
    }
}
