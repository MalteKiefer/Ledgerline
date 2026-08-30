<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class InvoiceReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'level' => ['required', 'integer', 'min:1', 'max:3'],
            'recipient' => ['nullable', 'string', 'email:rfc', 'max:320'],
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

    public function level(): int
    {
        return $this->integer('level');
    }

    public function recipient(): ?string
    {
        $value = $this->validated('recipient');

        return is_string($value) ? $value : null;
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
