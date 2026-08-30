<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Payments;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\AllocationLineData;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Shared\Money;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class AllocatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.invoice_id' => ['required', 'string', 'uuid'],
            'lines.*.amount' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
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

    /**
     * Resolves every line's public invoice UUID to the owner-scoped
     * internal ID through the repository — plumbing, not a second command.
     *
     * @return list<AllocationLineData>
     */
    public function lines(InvoiceRepository $invoices): array
    {
        $sourceLines = $this->validated('lines');
        if (! is_array($sourceLines)) {
            throw new InvalidArgumentException('Validated allocation lines are missing.');
        }

        $lines = [];
        foreach ($sourceLines as $line) {
            if (! is_array($line) || ! is_string($line['invoice_id'] ?? null) || ! is_string($line['amount'] ?? null)) {
                throw new InvalidArgumentException('Validated allocation line is invalid.');
            }
            $invoiceId = $invoices->idForUuid($line['invoice_id']);
            $lines[] = new AllocationLineData(
                $invoiceId,
                Money::fromDecimal($line['amount'], 'XXX')->minor(),
            );
        }

        return $lines;
    }

    public function expectedVersion(): ?int
    {
        return $this->filled('expected_version') ? $this->integer('expected_version') : null;
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
