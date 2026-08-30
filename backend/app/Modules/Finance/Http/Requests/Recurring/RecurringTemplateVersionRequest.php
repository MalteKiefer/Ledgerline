<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Recurring;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Http\Requests\Invoices\BuildsInvoiceDraftData;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class RecurringTemplateVersionRequest extends FormRequest
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
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'expected_version' => ['required', 'integer', 'min:0'],
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

    public function versionData(): RecurringTemplateVersionData
    {
        $data = $this->validated();
        $effectiveFrom = $data['effective_from'] ?? null;
        $draft = $data['draft'] ?? null;
        if (! is_string($effectiveFrom) || ! is_array($draft)) {
            throw new InvalidArgumentException('Validated recurring template version is invalid.');
        }

        return new RecurringTemplateVersionData(new DateTimeImmutable($effectiveFrom), $this->buildDraft($draft));
    }

    public function expectedVersion(): int
    {
        return $this->integer('expected_version');
    }

    public function idempotencyKey(): IdempotencyKey
    {
        $value = $this->validated('idempotency_key');
        if (! is_string($value)) {
            throw new InvalidArgumentException('Idempotency-Key header is required.');
        }

        return new IdempotencyKey($value);
    }
}
