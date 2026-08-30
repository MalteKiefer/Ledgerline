<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Payments;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class ReverseAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_payment_version' => ['nullable', 'integer', 'min:0'],
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

    public function expectedPaymentVersion(): ?int
    {
        return $this->filled('expected_payment_version') ? $this->integer('expected_payment_version') : null;
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
