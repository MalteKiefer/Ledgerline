<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Shared parsing between the invoice draft request and the recurring
 * template request, which embeds the exact same draft shape. Both convert
 * client-editable decimal strings into the authoritative minor-unit /
 * basis-point integers that InvoiceDraftData requires; the server always
 * recalculates totals downstream, this only builds an exact input snapshot.
 */
trait BuildsInvoiceDraftData
{
    /** @return array<string, mixed> */
    protected function draftRules(string $prefix = ''): array
    {
        return [
            $prefix.'issue_date' => ['required', 'date_format:Y-m-d'],
            $prefix.'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$prefix.'issue_date'],
            $prefix.'currency' => ['required', 'string', 'regex:/\A[A-Z]{3}\z/D'],
            $prefix.'customer' => ['required', 'array'],
            $prefix.'customer.name' => ['required', 'string', 'max:255'],
            $prefix.'customer.email' => ['nullable', 'string', 'email:rfc', 'max:320'],
            // The dedicated billing contact (Rechnungs-E-Mail): preferred over
            // customer.email as the delivery recipient when present — see
            // EloquentInvoiceRepository::assertDeliveryReady().
            $prefix.'customer.invoiceEmail' => ['nullable', 'string', 'email:rfc', 'max:320'],
            $prefix.'partner_id' => ['nullable', 'integer', 'min:1'],
            $prefix.'project_id' => ['nullable', 'integer', 'min:1'],
            $prefix.'lines' => ['required', 'array', 'min:1', 'max:200'],
            $prefix.'lines.*.description' => ['required', 'string', 'max:1000'],
            $prefix.'lines.*.quantity' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D'],
            $prefix.'lines.*.unit' => ['required', 'string', 'max:50'],
            $prefix.'lines.*.unit_price' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            $prefix.'lines.*.tax_rate' => ['required', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            $prefix.'lines.*.kind' => ['required', 'string', 'in:service,hardware'],
            $prefix.'lines.*.product_id' => ['nullable', 'integer', 'min:1'],
            $prefix.'discount_type' => ['required', 'string', 'in:none,percent,fixed'],
            $prefix.'discount_value' => ['nullable', 'string', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D'],
            $prefix.'control_net_minor' => ['nullable', 'string', 'regex:/\A(?:0|-?[1-9][0-9]*)\z/D'],
            $prefix.'control_vat_minor' => ['nullable', 'string', 'regex:/\A(?:0|-?[1-9][0-9]*)\z/D'],
            $prefix.'control_gross_minor' => ['nullable', 'string', 'regex:/\A(?:0|-?[1-9][0-9]*)\z/D'],
        ];
    }

    /** @param array<array-key, mixed> $data */
    protected function buildDraft(array $data): InvoiceDraftData
    {
        $currency = $this->requiredString($data, 'currency');
        $sourceLines = $data['lines'] ?? null;
        if (! is_array($sourceLines)) {
            throw new InvalidArgumentException('Validated invoice lines are missing.');
        }
        $lines = [];
        foreach ($sourceLines as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException('Validated invoice line is invalid.');
            }
            $lines[] = new InvoiceLineData(
                $this->requiredString($line, 'description'),
                $this->canonicalQuantity($this->requiredString($line, 'quantity')),
                Money::fromDecimal($this->requiredString($line, 'unit_price'), $currency)->minor(),
                Money::fromDecimal($this->requiredString($line, 'tax_rate'), 'BPS')->minor(),
                $this->requiredString($line, 'unit'),
                $this->nullableInt($line, 'product_id'),
                $this->requiredString($line, 'kind'),
            );
        }

        $sourceCustomer = $data['customer'] ?? null;
        if (! is_array($sourceCustomer)) {
            throw new InvalidArgumentException('Validated invoice customer is missing.');
        }
        $customer = [];
        foreach ($sourceCustomer as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Validated invoice customer key is invalid.');
            }
            $customer[$key] = $value;
        }

        return new InvoiceDraftData(
            new DateTimeImmutable($this->requiredString($data, 'issue_date')),
            new DateTimeImmutable($this->requiredString($data, 'due_date')),
            $currency,
            $customer,
            $lines,
            $this->buildDiscount($data, $currency),
            $this->nullableExactInteger($data, 'control_net_minor'),
            $this->nullableExactInteger($data, 'control_vat_minor'),
            $this->nullableExactInteger($data, 'control_gross_minor'),
            $this->nullableInt($data, 'partner_id'),
            $this->nullableInt($data, 'project_id'),
        );
    }

    /** @param array<array-key, mixed> $data */
    private function buildDiscount(array $data, string $currency): Discount
    {
        $type = $this->requiredString($data, 'discount_type');
        $value = $this->nullableString($data, 'discount_value');

        return match ($type) {
            'none' => Discount::none($currency),
            'percent' => Discount::percentBasisPoints(
                Money::fromDecimal($value ?? throw new InvalidArgumentException('Percent discount value is required.'), 'BPS')->minor(),
                $currency,
            ),
            'fixed' => Discount::fixed(Money::fromDecimal(
                $value ?? throw new InvalidArgumentException('Fixed discount value is required.'),
                $currency,
            )),
            default => throw new InvalidArgumentException('Invoice discount type is invalid.'),
        };
    }

    private function canonicalQuantity(string $quantity): string
    {
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,4}))?\z/D', $quantity, $parts) !== 1) {
            throw new InvalidArgumentException('Invoice line quantity is invalid.');
        }

        return $parts[1].$parts[2].'.'.str_pad($parts[3] ?? '', 4, '0');
    }

    /** @param array<array-key, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("Validated invoice {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException("Validated invoice {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value !== null && ! is_int($value)) {
            throw new InvalidArgumentException("Validated invoice {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function nullableExactInteger(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_string($value) || ! is_int($integer)) {
            throw new InvalidArgumentException("Validated invoice {$key} is outside the supported integer range.");
        }

        return $integer;
    }
}
