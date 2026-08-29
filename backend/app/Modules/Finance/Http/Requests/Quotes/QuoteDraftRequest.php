<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use Illuminate\Foundation\Http\FormRequest;

final class QuoteDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['nullable', 'string', 'email:rfc', 'max:320'],
            'issue_date' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/D'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D'],
            'lines.*.unit' => ['required', 'string', 'max:50'],
            'lines.*.unit_price' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            'lines.*.tax_rate' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            'lines.*.kind' => ['required', 'string', 'in:service,hardware,expense'],
            'lines.*.product_id' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['required', 'string', 'in:none,percent,fixed'],
            'discount_value' => ['nullable', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            'intro_text' => ['nullable', 'string'],
            'outro_text' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'string'],
            'control_net_minor' => ['nullable', 'integer'],
            'control_vat_minor' => ['nullable', 'integer'],
            'control_gross_minor' => ['nullable', 'integer'],
        ];

        if ($this->routeIs('api.finance-v2.quotes.store')) {
            $rules['idempotency_key'] = ['required', 'string', 'max:255'];
        }
        if ($this->routeIs('api.finance-v2.quotes.draft.update')) {
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

    public function draft(): QuoteDraftData
    {
        $data = $this->validated();
        $sourceLines = $data['lines'] ?? null;
        if (! is_array($sourceLines)) {
            throw new \LogicException('Validated quote lines are missing.');
        }
        $lines = [];
        foreach ($sourceLines as $line) {
            if (! is_array($line)) {
                throw new \LogicException('Validated quote line is invalid.');
            }
            $lines[] = new QuoteLineData(
                $this->requiredString($line, 'description'),
                $this->requiredString($line, 'quantity'),
                $this->requiredString($line, 'unit'),
                $this->requiredString($line, 'unit_price'),
                $this->requiredString($line, 'tax_rate'),
                $this->requiredString($line, 'kind'),
                $this->nullableInt($line, 'product_id'),
            );
        }

        $sourceCustomer = $data['customer'] ?? null;
        if (! is_array($sourceCustomer)) {
            throw new \LogicException('Validated quote customer is missing.');
        }
        $customer = [];
        foreach ($sourceCustomer as $key => $value) {
            if (! is_string($key)) {
                throw new \LogicException('Validated quote customer key is invalid.');
            }
            $customer[$key] = $value;
        }

        return new QuoteDraftData(
            $this->requiredString($data, 'title'),
            $this->nullableInt($data, 'partner_id'),
            $customer,
            $this->nullableString($data, 'issue_date'),
            $this->nullableString($data, 'valid_until'),
            $this->requiredString($data, 'currency'),
            $lines,
            $this->requiredString($data, 'discount_type'),
            $this->nullableString($data, 'discount_value'),
            $this->nullableString($data, 'intro_text'),
            $this->nullableString($data, 'outro_text'),
            $this->nullableString($data, 'internal_note'),
            $this->nullableInt($data, 'control_net_minor'),
            $this->nullableInt($data, 'control_vat_minor'),
            $this->nullableInt($data, 'control_gross_minor'),
        );
    }

    public function idempotencyKey(): string
    {
        return $this->requiredString($this->validated(), 'idempotency_key');
    }

    public function expectedVersion(): int
    {
        return $this->integer('version');
    }

    /** @param array<array-key, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new \LogicException("Validated quote {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new \LogicException("Validated quote {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value !== null && ! is_int($value)) {
            throw new \LogicException("Validated quote {$key} is invalid.");
        }

        return $value;
    }
}
