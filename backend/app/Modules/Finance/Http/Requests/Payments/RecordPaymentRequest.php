<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Payments;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/D'],
            'received_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'reference' => ['nullable', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
            'source_type' => ['nullable', 'string', 'max:64'],
            'source_key' => ['nullable', 'string', 'max:255'],
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

    public function paymentData(): RecordPaymentData
    {
        $amount = $this->requiredString('amount');
        $currency = $this->requiredString('currency');

        return new RecordPaymentData(
            Money::fromDecimal($amount, $currency)->minor(),
            $currency,
            new DateTimeImmutable($this->requiredString('received_at')),
            $this->nullableString('reference'),
            $this->nullableString('counterparty'),
            $this->filled('payment_method_id') ? $this->integer('payment_method_id') : null,
            $this->nullableString('source_type'),
            $this->nullableString('source_key'),
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

    private function requiredString(string $key): string
    {
        $value = $this->validated($key);
        if (! is_string($value)) {
            throw new InvalidArgumentException("Validated payment {$key} is invalid.");
        }

        return $value;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
