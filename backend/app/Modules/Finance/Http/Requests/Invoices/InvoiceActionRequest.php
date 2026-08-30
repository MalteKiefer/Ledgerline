<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Covers the version-only draft delete and the idempotency-key-only
 * finalize/cancel actions. Which fields are required depends on the named
 * route, mirroring the equivalent QuoteActionRequest.
 */
final class InvoiceActionRequest extends FormRequest
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
            'api.finance-v2.invoices.finalize',
            'api.finance-v2.invoices.cancel',
        ], true);
        $versioned = $name === 'api.finance-v2.invoices.destroy';

        return [
            'version' => [$versioned ? 'required' : 'nullable', 'integer', 'min:0'],
            'idempotency_key' => [$idempotent ? 'required' : 'nullable', 'string', 'max:255'],
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

    public function idempotencyKey(): IdempotencyKey
    {
        $value = $this->validated('idempotency_key');
        if (! is_string($value)) {
            throw new InvalidArgumentException('Idempotency-Key header is required.');
        }

        return new IdempotencyKey($value);
    }
}
