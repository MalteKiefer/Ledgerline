<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Recurring;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Domain\Recurring\RecurrenceInterval;
use App\Modules\Finance\Http\Requests\Invoices\BuildsInvoiceDraftData;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use ValueError;

final class RecurringTemplateRequest extends FormRequest
{
    use BuildsInvoiceDraftData;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'mode' => ['required', 'string', 'in:draft,auto_send'],
            'interval' => ['required', 'string', 'in:monthly,quarterly,semiannual,annual'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after:start_date'],
            'run_time' => ['required', 'date_format:H:i:s'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];

        return array_merge($rules, $this->draftRules('draft.'));
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

    public function templateData(): RecurringTemplateData
    {
        $data = $this->validated();
        $mode = $this->requiredString($data, 'mode');
        $startDate = $this->requiredString($data, 'start_date');
        $draft = $data['draft'] ?? null;
        if (! is_array($draft)) {
            throw new InvalidArgumentException('Validated recurring template draft is missing.');
        }

        try {
            $interval = RecurrenceInterval::from($this->requiredString($data, 'interval'));
        } catch (ValueError) {
            throw new InvalidArgumentException('Recurring template interval is invalid.');
        }

        return new RecurringTemplateData(
            $mode,
            $interval,
            $this->requiredString($data, 'timezone'),
            new DateTimeImmutable($startDate),
            isset($data['end_date']) && is_string($data['end_date']) ? new DateTimeImmutable($data['end_date']) : null,
            $this->requiredString($data, 'run_time'),
            new RecurringTemplateVersionData(new DateTimeImmutable($startDate), $this->buildDraft($draft)),
        );
    }

    public function idempotencyKey(): IdempotencyKey
    {
        $value = $this->validated('idempotency_key');
        if (! is_string($value)) {
            throw new InvalidArgumentException('Idempotency-Key header is required.');
        }

        return new IdempotencyKey($value);
    }

    /** @param array<array-key, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("Validated recurring template {$key} is invalid.");
        }

        return $value;
    }
}
