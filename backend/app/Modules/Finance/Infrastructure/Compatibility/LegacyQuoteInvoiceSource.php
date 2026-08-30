<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinanceQuote;
use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraftFromSource;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;

/**
 * Converts one legacy `FinanceQuote` row (the pre-module quotes screen still
 * routed at `/finance/quotes/{quote}/convert`, distinct from the newer
 * hexagonal Quote module) into a finance-v2 invoice draft. It canonicalizes
 * the exact quote row into a frozen source snapshot, uses
 * `sourceType='legacy_quote_snapshot'` with the quote's own ID as source
 * revision identity and its SHA-256, then calls the same
 * `CreateInvoiceDraftFromSource` pipeline every other invoice origin uses.
 *
 * This class only reads the quote and calls the invoice command — it never
 * mutates the `FinanceQuote` row or re-evaluates its status; the caller
 * (`FinanceQuoteController::convertToInvoice`) owns locking the quote,
 * checking eligibility, and stamping `converted_invoice_id` in the same
 * transaction.
 */
final readonly class LegacyQuoteInvoiceSource
{
    public function __construct(private CreateInvoiceDraftFromSource $create) {}

    public function convert(int $ownerId, FinanceQuote $quote, int $paymentTermsDays): InvoiceView
    {
        if ((int) $quote->user_id !== $ownerId) {
            throw new DomainException('legacy_quote_owner_mismatch');
        }

        $currency = (string) $quote->currency;
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new DomainException('legacy_quote_currency_unreadable');
        }

        $customer = $quote->customer;
        if (! is_array($customer) || ! isset($customer['name']) || ! is_string($customer['name']) || trim($customer['name']) === '') {
            throw new DomainException('legacy_quote_customer_invalid');
        }

        $rawLines = $quote->lines;
        if (! is_array($rawLines) || $rawLines === []) {
            throw new DomainException('legacy_quote_lines_invalid');
        }
        $lines = [];
        foreach ($rawLines as $rawLine) {
            $lines[] = $this->line($rawLine, $currency);
        }

        $issueDate = new DateTimeImmutable('today');
        $dueDate = $issueDate->modify(sprintf('+%d days', max(0, $paymentTermsDays)));

        $net = $quote->net;
        $vat = $quote->vat;
        $gross = $quote->gross;

        $draft = new InvoiceDraftData(
            issueDate: $issueDate,
            dueDate: $dueDate,
            currency: $currency,
            customer: $customer,
            lines: $lines,
            discount: $this->discount((string) ($quote->discount_type ?? ''), $quote->discount_value, $currency),
            controlNetMinor: $net !== null ? Money::fromDecimal((string) $net, $currency)->minor() : null,
            controlVatMinor: $vat !== null ? Money::fromDecimal((string) $vat, $currency)->minor() : null,
            controlGrossMinor: $gross !== null ? Money::fromDecimal((string) $gross, $currency)->minor() : null,
            partnerId: $quote->partner_id !== null ? (int) $quote->partner_id : null,
        );

        $sourceKey = (string) $quote->id;
        $snapshotForHash = [
            'id' => (int) $quote->id,
            'number' => $quote->number,
            'currency' => $currency,
            'customer' => $customer,
            'lines' => $rawLines,
            'discount_type' => $quote->discount_type,
            'discount_value' => $quote->discount_value,
            'net' => $net,
            'vat' => $vat,
            'gross' => $gross,
        ];
        $snapshotSha256 = hash('sha256', json_encode($snapshotForHash, JSON_THROW_ON_ERROR));

        $source = new InvoiceDraftSource('legacy_quote_snapshot', $sourceKey, (int) $quote->id, $snapshotSha256, $draft);

        return $this->create->handle($source, new IdempotencyKey('legacy-quote-convert:'.$quote->id));
    }

    /** @param array<array-key, mixed> $rawLine */
    private function line(mixed $rawLine, string $currency): InvoiceLineData
    {
        if (! is_array($rawLine)
            || ! is_string($rawLine['desc'] ?? null) || trim($rawLine['desc']) === ''
            || ! is_string($rawLine['unit'] ?? null) || trim($rawLine['unit']) === ''
            || ! isset($rawLine['qty']) || ! isset($rawLine['unitPrice']) || ! isset($rawLine['vatRate'])) {
            throw new DomainException('legacy_quote_lines_invalid');
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
            throw new DomainException('legacy_quote_lines_invalid');
        }

        try {
            $quantity = $this->canonicalQuantity((string) $qty);
            $unitPriceMinor = Money::fromDecimal((string) $unitPrice, $currency)->minor();
            $taxRateBasisPoints = Money::fromDecimal((string) $vatRate, 'BPS')->minor();
        } catch (\Throwable $exception) {
            throw new DomainException('legacy_quote_lines_invalid', previous: $exception);
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
            throw new DomainException('legacy_quote_lines_invalid');
        }

        return $parts[1].$parts[2].'.'.str_pad($parts[3] ?? '', 4, '0');
    }

    private function discount(string $type, mixed $value, string $currency): Discount
    {
        if ($type === '' || $type === 'none') {
            return Discount::none($currency);
        }
        if (! is_string($value) && ! is_numeric($value)) {
            throw new DomainException('legacy_quote_discount_invalid');
        }
        $decimal = (string) $value;

        return match ($type) {
            'percent' => Discount::percentBasisPoints(
                Money::fromDecimal($decimal, 'BPS')->minor(),
                $currency,
            ),
            'amount', 'fixed' => Discount::fixed(Money::fromDecimal($decimal, $currency)),
            default => throw new DomainException('legacy_quote_discount_invalid'),
        };
    }
}
