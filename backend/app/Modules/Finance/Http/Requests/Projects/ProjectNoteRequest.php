<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Projects;

use App\Modules\Finance\Application\DTOs\Projects\AppendDocumentNoteData;
use App\Modules\Finance\Application\DTOs\Projects\AppendProjectNoteData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectNoteFilter;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ProjectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            if ($this->routeIs('api.finance-v2.projects.activities.index')) {
                return ['cursor' => ['nullable', 'string', 'max:4096'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
            }

            return [
                'q' => ['nullable', 'string', 'max:255'],
                'types' => ['nullable', 'array', 'max:6'],
                'types.*' => ['required', 'string', 'distinct', 'in:note,decision,call,email,meeting,correction'],
                'visibilities' => ['nullable', 'array', 'max:2'],
                'visibilities.*' => ['required', 'string', 'distinct', 'in:internal,customer'],
                'author_id' => ['nullable', 'integer', 'min:1'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ];
        }

        $rules = [
            'type' => ['required', 'string', 'in:note,decision,call,email,meeting,correction'],
            'visibility' => ['required', 'string', 'in:internal,customer'],
            'body' => ['required', 'string', 'min:1', 'max:100000'],
            'supersedes_note_id' => ['nullable', 'integer', 'min:1', 'required_if:type,correction', 'prohibited_unless:type,correction'],
        ];
        if ($this->routeIs('api.finance-v2.document-series.notes.store')) {
            $rules['revision_id'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function filter(): ProjectNoteFilter
    {
        $data = $this->validated();

        return new ProjectNoteFilter($this->stringOrNull($data, 'q'), $this->strings($data, 'types'), $this->strings($data, 'visibilities'),
            $this->intOrNull($data, 'author_id'), $this->dateOrNull($data, 'from'), $this->dateOrNull($data, 'to'),
            $this->intOrDefault($data, 'page', 1), $this->intOrDefault($data, 'per_page', 50));
    }

    public function projectData(ProjectId $projectId): AppendProjectNoteData
    {
        $data = $this->validated();

        return new AppendProjectNoteData(
            $projectId,
            $this->requiredString($data, 'type'),
            $this->requiredString($data, 'visibility'),
            $this->requiredString($data, 'body'),
            $projectId->ownerId,
            new DateTimeImmutable,
            $this->intOrNull($data, 'supersedes_note_id'),
        );
    }

    public function documentData(int $ownerId, string $series): AppendDocumentNoteData
    {
        $data = $this->validated();

        return new AppendDocumentNoteData(
            $ownerId,
            $series,
            $this->intOrNull($data, 'revision_id'),
            $this->requiredString($data, 'type'),
            $this->requiredString($data, 'visibility'),
            $this->requiredString($data, 'body'),
            $ownerId,
            new DateTimeImmutable,
            $this->intOrNull($data, 'supersedes_note_id'),
        );
    }

    public function cursor(): ?string
    {
        $value = $this->validated('cursor');

        return is_string($value) ? $value : null;
    }

    public function perPage(): int
    {
        $value = $this->validated('per_page');
        if ($value === null) {
            return 50;
        }
        if (! is_int($value) && ! is_string($value)) {
            throw new \LogicException('Validated per_page is not an integer.');
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_int($parsed)) {
            throw new \LogicException('Validated per_page is outside the supported integer range.');
        }

        return $parsed;
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

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function strings(array $data, string $key): array
    {
        $values = $data[$key] ?? [];
        if (! is_array($values)) {
            throw new \LogicException("Validated {$key} is not an array.");
        }
        $result = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new \LogicException("Validated {$key} contains a non-string value.");
            }
            $result[] = $value;
        }

        return $result;
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
        $value = $this->stringOrNull($data, $key);
        if ($value === null) {
            throw new \LogicException("Validated {$key} is missing.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function intOrNull(array $data, string $key): ?int
    {
        if (! isset($data[$key])) {
            return null;
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

    /** @param array<string, mixed> $data */
    private function intOrDefault(array $data, string $key, int $default): int
    {
        return $this->intOrNull($data, $key) ?? $default;
    }
}
