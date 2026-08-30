<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateLedgerEntryData;
use App\Modules\Finance\Application\DTOs\Projects\CreateWorkItemData;
use App\Modules\Finance\Application\DTOs\Projects\LogTimeData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\UpdateTimeData;
use App\Modules\Finance\Application\DTOs\Projects\UpdateWorkItemData;
use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ProjectWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $page = [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
        if ($this->isMethod('GET')) {
            if ($this->routeIs('api.finance-v2.projects.ledger.index')) {
                return $page + [
                    'direction' => ['nullable', 'string', 'in:in,out'],
                    'from' => ['nullable', 'date_format:Y-m-d'],
                    'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
                    'category_reference' => ['nullable', 'string', 'max:255'],
                ];
            }

            return $page;
        }

        if ($this->routeIs('api.finance-v2.projects.work-items.reorder')) {
            return ['ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['required', 'uuid', 'distinct']];
        }
        if ($this->routeIs('api.finance-v2.projects.invoice-drafts.store')) {
            return [
                'time_entry_ids' => ['required', 'array', 'min:1', 'max:500'],
                'time_entry_ids.*' => ['required', 'uuid', 'distinct'],
                'idempotency_key' => ['required', 'string', 'max:255'],
            ];
        }

        $destroy = $this->isMethod('DELETE');
        if ($destroy) {
            return ['version' => ['required', 'integer', 'min:0']];
        }

        if ($this->routeIs('api.finance-v2.projects.work-items.store', 'api.finance-v2.projects.work-items.update')) {
            $rules = [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                'status' => ['required', 'string', 'in:open,in_progress,done'],
                'starts_on' => ['nullable', 'date_format:Y-m-d'],
                'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'estimate_hours' => ['nullable', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D'],
                'is_milestone' => ['required', 'boolean'],
                'product_reference' => ['nullable', 'string', 'max:255'],
            ];
            if ($this->routeIs('api.finance-v2.projects.work-items.update')) {
                $rules['version'] = ['required', 'integer', 'min:0'];
            }

            return $rules;
        }

        if ($this->routeIs('api.finance-v2.projects.time-entries.store', 'api.finance-v2.projects.time-entries.update')) {
            $rules = [
                'work_item_id' => ['nullable', 'uuid'],
                'worked_on' => ['required', 'date_format:Y-m-d'],
                'hours' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D'],
                'description' => ['nullable', 'string', 'max:10000'],
                'billable' => ['required', 'boolean'],
                'hourly_rate_minor' => ['nullable', 'string', 'regex:/\A(?:0|[1-9][0-9]*)\z/D'],
                'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/D'],
            ];
            if ($this->routeIs('api.finance-v2.projects.time-entries.update')) {
                $rules['version'] = ['required', 'integer', 'min:0'];
            }

            return $rules;
        }

        $rules = [
            'direction' => ['required', 'string', 'in:in,out'],
            'amount_minor' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]*)\z/D'],
            'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/D'],
            'occurred_on' => ['nullable', 'date_format:Y-m-d'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:10000'],
            'category_reference' => ['nullable', 'string', 'max:255'],
            'payment_method_reference' => ['nullable', 'string', 'max:255'],
        ];
        if ($this->routeIs('api.finance-v2.projects.ledger.update')) {
            $rules['version'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
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

    public function createWorkData(ProjectId $projectId): CreateWorkItemData
    {
        $data = $this->validated();

        return new CreateWorkItemData($projectId, $this->requiredString($data, 'title'), $projectId->ownerId, new DateTimeImmutable,
            $this->stringOrNull($data, 'description'), WorkItemStatus::from($this->requiredString($data, 'status')),
            $this->dateOrNull($data, 'starts_on'), $this->dateOrNull($data, 'due_on'),
            $this->stringOrNull($data, 'estimate_hours'), $this->requiredBool($data, 'is_milestone'), $this->stringOrNull($data, 'product_reference'));
    }

    public function updateWorkData(ProjectId $projectId, string $uuid): UpdateWorkItemData
    {
        $data = $this->validated();

        return new UpdateWorkItemData($projectId, $uuid, $this->requiredInt($data, 'version'), $this->requiredString($data, 'title'),
            WorkItemStatus::from($this->requiredString($data, 'status')), $projectId->ownerId, new DateTimeImmutable,
            $this->stringOrNull($data, 'description'), $this->dateOrNull($data, 'starts_on'), $this->dateOrNull($data, 'due_on'),
            $this->stringOrNull($data, 'estimate_hours'), $this->requiredBool($data, 'is_milestone'), $this->stringOrNull($data, 'product_reference'));
    }

    public function createTimeData(ProjectId $projectId): LogTimeData
    {
        $data = $this->validated();
        $rate = $this->minorOrNull($data, 'hourly_rate_minor');

        $currency = $this->requiredString($data, 'currency');

        return new LogTimeData($projectId, $this->stringOrNull($data, 'work_item_id'), new DateTimeImmutable($this->requiredString($data, 'worked_on')),
            $this->requiredString($data, 'hours'), $projectId->ownerId, new DateTimeImmutable, $this->stringOrNull($data, 'description'),
            $this->requiredBool($data, 'billable'), $rate === null ? null : Money::fromMinor($rate, $currency), $currency);
    }

    public function updateTimeData(ProjectId $projectId, string $uuid): UpdateTimeData
    {
        $data = $this->validated();
        $rate = $this->minorOrNull($data, 'hourly_rate_minor');

        $currency = $this->requiredString($data, 'currency');

        return new UpdateTimeData($projectId, $uuid, $this->requiredInt($data, 'version'), $this->stringOrNull($data, 'work_item_id'),
            new DateTimeImmutable($this->requiredString($data, 'worked_on')), $this->requiredString($data, 'hours'), $projectId->ownerId, new DateTimeImmutable,
            $this->stringOrNull($data, 'description'), $this->requiredBool($data, 'billable'),
            $rate === null ? null : Money::fromMinor($rate, $currency), $currency);
    }

    public function ledgerData(ProjectId $projectId): CreateLedgerEntryData
    {
        $data = $this->validated();

        return new CreateLedgerEntryData($projectId, $this->requiredString($data, 'direction'), $this->minor($data, 'amount_minor'),
            $this->requiredString($data, 'currency'), $projectId->ownerId, new DateTimeImmutable, $this->dateOrNull($data, 'occurred_on'),
            $this->stringOrNull($data, 'title'), $this->stringOrNull($data, 'note'),
            $this->stringOrNull($data, 'category_reference'), $this->stringOrNull($data, 'payment_method_reference'));
    }

    public function page(): int
    {
        return $this->validatedIntOrDefault('page', 1);
    }

    public function perPage(): int
    {
        return $this->validatedIntOrDefault('per_page', 50);
    }

    public function expectedVersion(): int
    {
        return $this->validatedIntOrDefault('version', -1);
    }

    /** @return list<string> */
    public function ids(string $key = 'ids'): array
    {
        $values = $this->validated($key);
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

    public function idempotencyKey(): string
    {
        $value = $this->validated('idempotency_key');
        if (! is_string($value)) {
            throw new \LogicException('Validated idempotency key is not a string.');
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->validated($key);
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new \LogicException("Validated {$key} is not a string.");
        }

        return $value;
    }

    public function optionalDate(string $key): ?DateTimeImmutable
    {
        $value = $this->optionalString($key);

        return $value === null ? null : new DateTimeImmutable($value);
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
    private function minorOrNull(array $data, string $key): ?int
    {
        return isset($data[$key]) ? $this->minor($data, $key) : null;
    }

    /** @param array<string, mixed> $data */
    private function minor(array $data, string $key): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_int($value)) {
            throw new \LogicException("Validated {$key} is outside the supported integer range.");
        }

        return $value;
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

    /** @param array<string, mixed> $data */
    private function requiredBool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (! is_bool($value)) {
            throw new \LogicException("Validated {$key} is not a boolean.");
        }

        return $value;
    }

    private function validatedIntOrDefault(string $key, int $default): int
    {
        $value = $this->validated($key);
        if ($value === null) {
            return $default;
        }
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
