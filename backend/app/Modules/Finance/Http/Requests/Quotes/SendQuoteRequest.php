<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

final class SendQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'recipient' => ['nullable', 'string', 'email:rfc', 'max:320'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:255'],
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

    public function idempotencyKey(): string
    {
        $value = $this->validated('idempotency_key');
        if (! is_string($value)) {
            throw new \LogicException('Validated quote idempotency key is invalid.');
        }

        return $value;
    }

    public function recipient(): ?string
    {
        $value = $this->validated('recipient');
        if ($value !== null && ! is_string($value)) {
            throw new \LogicException('Validated quote recipient is invalid.');
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
