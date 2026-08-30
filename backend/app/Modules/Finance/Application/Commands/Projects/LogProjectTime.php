<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\LogTimeData;
use App\Modules\Finance\Application\DTOs\Projects\TimeEntryView;
use App\Modules\Finance\Application\Ports\Projects\ProjectRateSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;

final readonly class LogProjectTime
{
    public function __construct(
        private ProjectWorkRepository $work,
        private ProjectRepository $projects,
        private ProjectRateSource $rates,
        private ProjectDataValidator $validator,
        private ProjectWorkflow $workflow,
    ) {}

    public function handle(LogTimeData $data): TimeEntryView
    {
        $this->validator->actor($data->projectId, $data->actorId);
        $project = $this->projects->get($data->projectId);
        $this->workflow->assertNotArchived($project->archived);
        if ($data->quantity->scaled() === 0) {
            throw new InvalidProjectAction('time_quantity_nonzero');
        }

        $rate = $data->hourlyRate ?? $this->rates->frozenRate(
            $data->projectId->ownerId,
            $project->partnerReference,
            $data->currency,
        );
        if ($data->billable && $rate === null) {
            throw new InvalidProjectAction('hourly_rate_required');
        }

        return $this->work->logTime($data, $rate);
    }
}
