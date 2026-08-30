<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\Invoice as LegacyInvoiceModel;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Invoices\LegacyInvoiceFinalization;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\Storage;

/**
 * Maps one legacy `invoices` row to the exact source contract the invoice
 * module already accepts from every other caller (quote conversion, project
 * time billing, recurring runs): an InvoiceDraftSource plus, for an
 * already-numbered row, the historical facts importFinalized() reproduces
 * verbatim. This class only reads and translates; it never writes to any
 * aggregate table directly — every mutation goes through ImportLegacyInvoice
 * (and, for money, RecordPayment/AllocatePayment).
 *
 * Fails closed (throws DomainException with a stable code) rather than
 * guessing whenever the legacy row cannot be reconstructed exactly:
 * unreadable currency, missing customer name, an unparsable line, or (for a
 * numbered row) a missing/unreadable PDF blob all stop that one invoice's
 * migration instead of inventing content.
 */
final readonly class LegacyInvoiceMapper
{
    /** @return array{source: InvoiceDraftSource, finalization: ?LegacyInvoiceFinalization} */
    public function map(LegacyInvoiceModel $legacy, ?int $cancelsInvoiceId): array
    {
        $currency = (string) $legacy->currency;
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new DomainException('legacy_invoice_currency_unreadable');
        }

        $customer = $legacy->customer;
        if (! is_array($customer) || ! isset($customer['name']) || ! is_string($customer['name']) || trim($customer['name']) === '') {
            throw new DomainException('legacy_invoice_customer_invalid');
        }
        $customerData = [];
        foreach ($customer as $key => $value) {
            if (! is_string($key)) {
                throw new DomainException('legacy_invoice_customer_invalid');
            }
            $customerData[$key] = $this->exactJsonValue($value);
        }

        $rawLines = $legacy->lines;
        if (! is_array($rawLines) || $rawLines === []) {
            throw new DomainException('legacy_invoice_lines_invalid');
        }
        $lines = [];
        foreach ($rawLines as $rawLine) {
            $lines[] = $this->line($rawLine, $currency);
        }

        $issueDate = $legacy->issue_date;
        $dueDate = $legacy->due_date;
        if ($issueDate === null) {
            throw new DomainException('legacy_invoice_dates_invalid');
        }
        $issue = new DateTimeImmutable($issueDate->format('Y-m-d'));
        $due = $dueDate !== null ? new DateTimeImmutable($dueDate->format('Y-m-d')) : $issue;
        if ($due < $issue) {
            $due = $issue;
        }

        $net = $legacy->net;
        $vat = $legacy->vat;
        $gross = $legacy->gross;
        if ($net === null || $vat === null || $gross === null) {
            throw new DomainException('legacy_invoice_totals_invalid');
        }

        $draft = new InvoiceDraftData(
            issueDate: $issue,
            dueDate: $due,
            currency: $currency,
            customer: $customerData,
            lines: $lines,
            discount: $this->discount((string) ($legacy->discount_type ?? ''), $legacy->discount_value, $currency),
            controlNetMinor: Money::fromDecimal((string) $net, $currency)->minor(),
            controlVatMinor: Money::fromDecimal((string) $vat, $currency)->minor(),
            controlGrossMinor: Money::fromDecimal((string) $gross, $currency)->minor(),
            partnerId: $legacy->partner_id !== null ? (int) $legacy->partner_id : null,
        );

        $sourceKey = (string) $legacy->id;
        $snapshotForHash = [
            'id' => (int) $legacy->id,
            'status' => (string) $legacy->status,
            'number' => $legacy->number,
            'year' => $legacy->year,
            'currency' => $currency,
            'customer' => $customerData,
            'lines' => $rawLines,
            'net' => (string) $net,
            'vat' => (string) $vat,
            'gross' => (string) $gross,
        ];
        $snapshotSha256 = hash('sha256', json_encode($snapshotForHash, JSON_THROW_ON_ERROR));

        $source = new InvoiceDraftSource(
            'legacy_invoice',
            $sourceKey,
            (int) $legacy->id,
            $snapshotSha256,
            $draft,
        );

        $status = (string) $legacy->status;
        $isNumbered = is_string($legacy->number) && trim($legacy->number) !== '';
        if ($status === 'draft' || ! $isNumbered) {
            return ['source' => $source, 'finalization' => null];
        }

        $yearValue = $legacy->year;
        $seqValue = $legacy->seq;
        if (! is_int($yearValue) || $yearValue < 1 || ! is_int($seqValue) || $seqValue < 1) {
            throw new DomainException('legacy_invoice_number_invalid');
        }

        $pdfBytes = $this->legacyPdfBytes($legacy);
        $finalizedAt = $legacy->created_at !== null
            ? DateTimeImmutable::createFromInterface($legacy->created_at)
            : new DateTimeImmutable($issue->format('Y-m-d').' 00:00:00');
        $sentAt = null;
        if ($legacy->sent_at !== null) {
            $sentAt = DateTimeImmutable::createFromInterface($legacy->sent_at);
        } elseif (in_array($status, ['sent', 'paid'], true)) {
            $sentAt = $legacy->updated_at !== null
                ? DateTimeImmutable::createFromInterface($legacy->updated_at)
                : $finalizedAt;
        }

        $finalization = new LegacyInvoiceFinalization(
            number: (string) $legacy->number,
            year: $yearValue,
            sequence: $seqValue,
            finalizedAt: $finalizedAt,
            sentAt: $sentAt,
            cancelsInvoiceId: $cancelsInvoiceId,
            pdfBytes: $pdfBytes,
        );

        return ['source' => $source, 'finalization' => $finalization];
    }

    /** @param array<array-key, mixed> $rawLine */
    private function line(mixed $rawLine, string $currency): InvoiceLineData
    {
        if (! is_array($rawLine)
            || ! is_string($rawLine['desc'] ?? null) || trim($rawLine['desc']) === ''
            || ! is_string($rawLine['unit'] ?? null) || trim($rawLine['unit']) === ''
            || ! isset($rawLine['qty']) || ! isset($rawLine['unitPrice']) || ! isset($rawLine['vatRate'])) {
            throw new DomainException('legacy_invoice_lines_invalid');
        }
        $kind = $rawLine['kind'] ?? 'service';
        if (! is_string($kind) || ! in_array($kind, ['service', 'hardware'], true)) {
            $kind = 'service';
        }
        $productId = $rawLine['productId'] ?? null;
        if ($productId !== null && (! is_int($productId) && ! is_numeric($productId))) {
            $productId = null;
        }

        $qty = $rawLine['qty'];
        $unitPrice = $rawLine['unitPrice'];
        $vatRate = $rawLine['vatRate'];
        if ((! is_string($qty) && ! is_numeric($qty))
            || (! is_string($unitPrice) && ! is_numeric($unitPrice))
            || (! is_string($vatRate) && ! is_numeric($vatRate))) {
            throw new DomainException('legacy_invoice_lines_invalid');
        }

        try {
            $quantity = $this->canonicalQuantity((string) $qty);
            $unitPriceMinor = Money::fromDecimal((string) $unitPrice, $currency)->minor();
            $taxRateBasisPoints = Money::fromDecimal((string) $vatRate, 'BPS')->minor();
        } catch (\Throwable $exception) {
            throw new DomainException('legacy_invoice_lines_invalid', previous: $exception);
        }

        return new InvoiceLineData(
            $rawLine['desc'],
            $quantity,
            $unitPriceMinor,
            $taxRateBasisPoints,
            $rawLine['unit'],
            $productId !== null ? (int) $productId : null,
            $kind,
        );
    }

    private function canonicalQuantity(string $quantity): string
    {
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,4}))?\z/D', trim($quantity), $parts) !== 1) {
            throw new DomainException('legacy_invoice_lines_invalid');
        }

        return $parts[1].$parts[2].'.'.str_pad($parts[3] ?? '', 4, '0');
    }

    private function discount(string $type, mixed $value, string $currency): Discount
    {
        if ($type === '' || $type === 'none') {
            return Discount::none($currency);
        }
        if (! is_string($value) && ! is_numeric($value)) {
            throw new DomainException('legacy_invoice_discount_invalid');
        }
        $decimal = (string) $value;

        return match ($type) {
            'percent' => Discount::percentBasisPoints(
                Money::fromDecimal($decimal, 'BPS')->minor(),
                $currency,
            ),
            'amount', 'fixed' => Discount::fixed(Money::fromDecimal($decimal, $currency)),
            default => throw new DomainException('legacy_invoice_discount_invalid'),
        };
    }

    private function legacyPdfBytes(LegacyInvoiceModel $legacy): string
    {
        $path = $legacy->pdf_path;
        if (! is_string($path) || trim($path) === '') {
            throw new DomainException('legacy_invoice_pdf_missing');
        }
        $disk = Storage::disk(is_string(config('files.disk')) ? config('files.disk') : 'local');
        if (! $disk->exists($path)) {
            throw new DomainException('legacy_invoice_pdf_missing');
        }
        $bytes = $disk->get($path);
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
            throw new DomainException('legacy_invoice_pdf_missing');
        }

        return $bytes;
    }

    /** Rejects floats anywhere in a legacy JSON value, matching InvoiceDraftData's own guard. */
    private function exactJsonValue(mixed $value): mixed
    {
        if (is_float($value)) {
            return is_finite($value) && $value === floor($value) ? (int) $value : (string) $value;
        }
        if (is_array($value)) {
            return array_map($this->exactJsonValue(...), $value);
        }

        return $value;
    }
}
