<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

final class QuoteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $name = (string) $this->route()?->getName();
        $idempotent = in_array($name, [
            'api.finance-v2.quotes.publish',
            'api.finance-v2.quotes.accept',
            'api.finance-v2.quotes.decline',
            'api.finance-v2.quotes.duplicate',
            'api.finance-v2.quotes.convert.invoice',
        ], true);
        $revisionBound = in_array($name, [
            'api.finance-v2.quotes.accept',
            'api.finance-v2.quotes.decline',
            'api.finance-v2.quotes.convert.invoice',
        ], true);

        return [
            'version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => [$idempotent ? 'required' : 'nullable', 'string', 'max:255'],
            'expected_revision_id' => [$revisionBound ? 'required' : 'nullable', 'integer', 'min:1'],
            'source_revision_id' => ['nullable', 'integer', 'min:1'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
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

    public function expectedVersion(): int
    {
        return $this->integer('version');
    }

    public function expectedRevisionId(): int
    {
        return $this->integer('expected_revision_id');
    }

    public function sourceRevisionId(): ?int
    {
        return $this->filled('source_revision_id') ? $this->integer('source_revision_id') : null;
    }

    public function idempotencyKey(): string
    {
        $value = $this->validated('idempotency_key');
        if (! is_string($value)) {
            throw new \LogicException('Validated quote idempotency key is invalid.');
        }

        return $value;
    }

    public function changeReason(): ?string
    {
        $value = $this->validated('change_reason');
        if ($value !== null && ! is_string($value)) {
            throw new \LogicException('Validated quote change reason is invalid.');
        }

        return $value;
    }
}
