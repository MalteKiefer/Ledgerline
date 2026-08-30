<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectResource extends JsonResource
{
    public function __construct(private readonly ProjectView $project)
    {
        parent::__construct($project);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->project->id->uuid,
            'parent_id' => $this->project->parentId?->uuid,
            'parent_available' => $this->project->parentAvailable,
            'name' => $this->project->name,
            'kind' => $this->project->kind->value,
            'status' => $this->project->status->value,
            'partner_reference' => $this->project->partnerReference,
            'starts_on' => $this->project->startsOn?->format('Y-m-d'),
            'due_on' => $this->project->dueOn?->format('Y-m-d'),
            'budget_minor' => $this->project->budgetMinor === null ? null : (string) $this->project->budgetMinor,
            'currency' => $this->project->currency,
            'version' => $this->project->version,
            'archived' => $this->project->archived,
            'created_at' => $this->project->createdAt->format('Y-m-d\TH:i:s.uP'),
            'updated_at' => $this->project->updatedAt->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
