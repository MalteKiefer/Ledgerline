<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectListFilter;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ProjectListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:planned,active,on_hold,done,cancelled'],
            'kind' => ['nullable', 'string', 'in:business,private'],
            'partner_reference' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'archived' => ['nullable', 'boolean'],
            'starts_from' => ['nullable', 'date_format:Y-m-d'],
            'starts_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_from'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],
            'sort' => ['nullable', 'string', 'in:updated_at,name,starts_on,due_on,status'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(int $ownerId): ProjectListFilter
    {
        $data = $this->validated();

        return new ProjectListFilter(
            $ownerId,
            $this->stringOrNull($data, 'q'),
            ($status = $this->stringOrNull($data, 'status')) !== null ? ProjectStatus::from($status) : null,
            ($kind = $this->stringOrNull($data, 'kind')) !== null ? ProjectKind::from($kind) : null,
            $this->stringOrNull($data, 'partner_reference'),
            $this->stringOrNull($data, 'parent_id'),
            $this->boolean('archived'),
            $this->dateOrNull($data, 'starts_from'),
            $this->dateOrNull($data, 'starts_to'),
            $this->dateOrNull($data, 'due_from'),
            $this->dateOrNull($data, 'due_to'),
            $this->stringOrDefault($data, 'sort', 'updated_at'),
            $this->stringOrDefault($data, 'direction', 'desc'),
            $this->intOrDefault($data, 'page', 1),
            $this->intOrDefault($data, 'per_page', 25),
        );
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
    private function stringOrDefault(array $data, string $key, string $default): string
    {
        return $this->stringOrNull($data, $key) ?? $default;
    }

    /** @param array<string, mixed> $data */
    private function intOrDefault(array $data, string $key, int $default): int
    {
        if (! isset($data[$key])) {
            return $default;
        }
        if (! is_int($data[$key]) && ! is_string($data[$key])) {
            throw new \LogicException("Validated {$key} is not an integer.");
        }
        $value = filter_var($data[$key], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_int($value)) {
            throw new \LogicException("Validated {$key} is outside the supported integer range.");
        }

        return $value;
    }
}
