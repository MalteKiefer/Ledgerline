<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ProjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('api.finance-v2.projects.documents.store')) {
            return [
                'source_type' => ['required', 'string', 'in:finance_series,legacy_invoice,file,gallery_photo,finance_receipt,bank_transaction,bank_transaction_receipt'],
                'source_reference' => ['required', 'string', 'max:255'],
                'pinned_revision_id' => ['nullable', 'integer', 'min:1'],
                'role' => ['required', 'string', 'in:source_quote,quote,invoice,payment,receipt,file,photo,other'],
                'idempotency_key' => ['required', 'string', 'max:255'],
            ];
        }
        if ($this->routeIs('api.finance-v2.projects.documents.destroy')) {
            return ['idempotency_key' => ['required', 'string', 'max:255']];
        }

        $common = [
            'q' => ['nullable', 'string', 'max:255'],
            'source_types' => ['nullable', 'array', 'max:7'],
            'source_types.*' => ['required', 'string', 'distinct', 'in:finance_series,legacy_invoice,file,gallery_photo,finance_receipt,bank_transaction,bank_transaction_receipt'],
            'mime_groups' => ['nullable', 'array', 'max:3'],
            'mime_groups.*' => ['required', 'string', 'distinct', 'in:pdf,image,other'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
        if ($this->routeIs('api.finance-v2.projects.document-sources.index')) {
            return $common + ['cursor' => ['nullable', 'string', 'max:4096']];
        }

        return $common + [
            'roles' => ['nullable', 'array', 'max:8'],
            'roles.*' => ['required', 'string', 'distinct', 'in:source_quote,quote,invoice,payment,receipt,file,photo,other'],
            'availabilities' => ['nullable', 'array', 'max:3'],
            'availabilities.*' => ['required', 'string', 'distinct', 'in:available,deleted,missing'],
            'state' => ['nullable', 'string', 'in:active,detached,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        $data = [];
        foreach ($this->all() as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }
        $data['idempotency_key'] = $this->header('Idempotency-Key');

        return $data;
    }

    public function documentFilter(ProjectId $projectId): ProjectDocumentFilter
    {
        $data = $this->validated();

        return new ProjectDocumentFilter($projectId, $this->stringOrNull($data, 'q'), $this->strings($data, 'source_types'),
            $this->strings($data, 'roles'), $this->strings($data, 'mime_groups'), $this->strings($data, 'availabilities'),
            $this->dateOrNull($data, 'from'), $this->dateOrNull($data, 'to'), $this->stringOrDefault($data, 'state', 'active'),
            $this->intOrDefault($data, 'page', 1), $this->intOrDefault($data, 'per_page', 50));
    }

    public function sourceFilter(int $ownerId): ProjectDocumentSourceFilter
    {
        $data = $this->validated();

        return new ProjectDocumentSourceFilter($ownerId, $this->stringOrNull($data, 'q'), $this->strings($data, 'source_types'),
            $this->strings($data, 'mime_groups'), $this->dateOrNull($data, 'from'), $this->dateOrNull($data, 'to'),
            $this->stringOrNull($data, 'cursor'), $this->intOrDefault($data, 'per_page', 50));
    }

    public function source(): ProjectDocumentSourceRef
    {
        $data = $this->validated();

        return new ProjectDocumentSourceRef(
            $this->requiredString($data, 'source_type'),
            $this->requiredString($data, 'source_reference'),
            $this->intOrNull($data, 'pinned_revision_id'),
        );
    }

    public function role(): string
    {
        return $this->validatedString('role');
    }

    public function idempotencyKey(): string
    {
        return $this->validatedString('idempotency_key');
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

    private function validatedString(string $key): string
    {
        $value = $this->validated($key);
        if (! is_string($value)) {
            throw new \LogicException("Validated {$key} is not a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function stringOrDefault(array $data, string $key, string $default): string
    {
        return $this->stringOrNull($data, $key) ?? $default;
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
