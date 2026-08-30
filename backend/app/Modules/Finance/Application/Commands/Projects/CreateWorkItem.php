<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateWorkItemData;
use App\Modules\Finance\Application\DTOs\Projects\WorkItemView;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;
use App\Modules\Finance\Domain\Projects\WorkItemWorkflow;
use InvalidArgumentException;

final readonly class CreateWorkItem
{
    public function __construct(
        private ProjectWorkRepository $work,
        private ProjectRepository $projects,
        private ProjectReferenceResolver $references,
        private ProjectDataValidator $validator,
        private ProjectWorkflow $projectWorkflow,
        private WorkItemWorkflow $workItemWorkflow,
    ) {}

    public function handle(CreateWorkItemData $data): WorkItemView
    {
        $this->validator->actor($data->projectId, $data->actorId);
        $project = $this->projects->get($data->projectId);
        $this->projectWorkflow->assertNotArchived($project->archived);
        $this->assertCommon($data);

        return $this->work->createWorkItem(new CreateWorkItemData(
            $data->projectId,
            trim($data->title),
            $data->actorId,
            $data->occurredAt,
            $data->description,
            $data->status,
            $data->startsOn,
            $data->dueOn,
            $data->estimate !== null ? $this->decimal($data->estimate->scaled()) : null,
            $data->isMilestone,
            $data->productReference,
        ));
    }

    private function assertCommon(CreateWorkItemData $data): void
    {
        if (trim($data->title) === '' || mb_strlen(trim($data->title)) > 255) {
            throw new InvalidArgumentException('work_item_title_invalid');
        }
        if ($data->startsOn !== null && $data->dueOn !== null && $data->startsOn > $data->dueOn) {
            throw new InvalidArgumentException('work_item_date_range_invalid');
        }
        if ($data->estimate !== null && $data->estimate->scaled() <= 0) {
            throw new InvalidProjectAction('work_item_estimate_invalid');
        }

        $this->workItemWorkflow->assertEstimateAllowed($data->isMilestone, $data->estimate);
        $this->references->assertOwnedProductReference($data->projectId->ownerId, $data->productReference);
    }

    private function decimal(int $scaled): string
    {
        return intdiv($scaled, 10_000).'.'.str_pad((string) ($scaled % 10_000), 4, '0', STR_PAD_LEFT);
    }
}
