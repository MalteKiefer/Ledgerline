<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use App\Modules\Finance\Domain\Projects\ProjectBudget;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ProjectWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', 'in:business,private'],
            'budget_minor' => ['nullable', 'string', 'regex:/\A(?:0|[1-9][0-9]*)\z/D'],
            'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/D'],
            'partner_reference' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
        if ($this->routeIs('api.finance-v2.projects.store')) {
            $rules['parent_id'] = ['nullable', 'uuid'];
        } else {
            $rules['version'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function createData(int $ownerId): CreateProjectData
    {
        $data = $this->validated();

        return new CreateProjectData(
            $ownerId,
            $this->requiredString($data, 'name'),
            ProjectKind::from($this->requiredString($data, 'kind')),
            ProjectBudget::fromMinor($this->minorOrNull($data, 'budget_minor'), $this->requiredString($data, 'currency')),
            $ownerId,
            new DateTimeImmutable,
            ($parentId = $this->stringOrNull($data, 'parent_id')) !== null ? new ProjectId($ownerId, $parentId) : null,
            $this->stringOrNull($data, 'partner_reference'),
            $this->dateOrNull($data, 'starts_on'),
            $this->dateOrNull($data, 'due_on'),
        );
    }

    public function updateData(ProjectId $projectId): UpdateProjectData
    {
        $data = $this->validated();

        return new UpdateProjectData(
            $projectId,
            $this->requiredInt($data, 'version'),
            $this->requiredString($data, 'name'),
            ProjectKind::from($this->requiredString($data, 'kind')),
            ProjectBudget::fromMinor($this->minorOrNull($data, 'budget_minor'), $this->requiredString($data, 'currency')),
            $projectId->ownerId,
            new DateTimeImmutable,
            $this->stringOrNull($data, 'partner_reference'),
            $this->dateOrNull($data, 'starts_on'),
            $this->dateOrNull($data, 'due_on'),
        );
    }

    /** @param array<string, mixed> $data */
    private function minorOrNull(array $data, string $key): ?int
    {
        if (! isset($data[$key])) {
            return null;
        }
        $value = filter_var($data[$key], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_int($value)) {
            throw new \LogicException("Validated {$key} is outside the supported integer range.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function stringOrNull(array $data, string $key): ?string
    {
        if (! isset($data[$key])) {
            return null;
        }
        if (! is_string($data[$key])) {
            throw new \LogicException("Validated {$key} is not a string.");
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function dateOrNull(array $data, string $key): ?DateTimeImmutable
    {
        $value = $this->stringOrNull($data, $key);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new \LogicException("Validated {$key} is not a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function requiredInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value) && ! is_string($value)) {
            throw new \LogicException("Validated {$key} is not an integer.");
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_int($parsed)) {
            throw new \LogicException("Validated {$key} is outside the supported integer range.");
        }

        return $parsed;
    }
}
