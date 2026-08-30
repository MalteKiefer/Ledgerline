<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinancePartner;
use App\Models\Invoice;
use App\Modules\Finance\Application\DTOs\Quotes\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Application\Ports\Quotes\QuoteToInvoicePort;
use DateTimeZone;
use LogicException;

final readonly class LegacyInvoiceDraftAdapter implements QuoteToInvoicePort
{
    public function __construct(
        private QuoteSettings $settings,
        private Clock $clock,
    ) {}

    public function createDraft(
        int $ownerId,
        QuoteRevisionRef $source,
        array $immutableSnapshot,
    ): InvoiceDraftTarget {
        if ($ownerId < 1 || ! hash_equals(
            $source->canonicalSnapshotSha256(),
            hash('sha256', json_encode($immutableSnapshot, JSON_THROW_ON_ERROR)),
        )) {
            throw new LogicException('Quote conversion requires the exact immutable revision snapshot.');
        }
        $customer = $immutableSnapshot['customer'] ?? null;
        $lines = $immutableSnapshot['lines'] ?? null;
        $totals = $immutableSnapshot['totals'] ?? null;
        $discount = $immutableSnapshot['discount'] ?? null;
        $currency = $immutableSnapshot['currency'] ?? null;
        if (! is_array($customer)
            || ! is_array($lines)
            || ! is_array($totals)
            || ! is_array($discount)
            || ! is_string($currency)) {
            throw new LogicException('Quote conversion snapshot is incomplete.');
        }
        $netMinor = $totals['net_minor'] ?? null;
        $vatMinor = $totals['vat_minor'] ?? null;
        $grossMinor = $totals['gross_minor'] ?? null;
        if (! is_int($netMinor) || ! is_int($vatMinor) || ! is_int($grossMinor)) {
            throw new LogicException('Quote conversion totals are missing.');
        }
        $legacyLines = $this->legacyLines($lines);
        [$discountType, $discountValue] = $this->legacyDiscount($discount);
        $timezone = new DateTimeZone($this->settings->ownerTimezone($ownerId));
        $issueDate = $this->clock->now()->setTimezone($timezone)->setTime(0, 0);
        $dueDate = $issueDate->modify(sprintf('+%d days', $this->settings->invoicePaymentTermsDays($ownerId)));
        $partnerId = $immutableSnapshot['partner_id'] ?? null;
        if ($partnerId !== null && ! is_int($partnerId)) {
            throw new LogicException('Quote conversion partner reference is invalid.');
        }
        if ($partnerId !== null) {
            FinancePartner::query()
                ->withoutGlobalScope('owner')
                ->where('finance_partners.user_id', $ownerId)
                ->whereKey($partnerId)
                ->firstOrFail(['id']);
        }

        $invoice = new Invoice;
        $invoice->forceFill([
            'user_id' => $ownerId,
            'number' => null,
            'seq' => null,
            'year' => null,
            'status' => 'draft',
            'type' => 'invoice',
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency' => $currency,
            'partner_id' => $partnerId,
            'customer' => $customer,
            'lines' => $legacyLines,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'net' => $this->decimal($netMinor),
            'vat' => $this->decimal($vatMinor),
            'gross' => $this->decimal($grossMinor),
            'note' => $immutableSnapshot['internal_note'] ?? $immutableSnapshot['customer_note'] ?? null,
            'imported' => false,
            'version' => 0,
            'version_seq' => 0,
            'pdf_path' => null,
        ])->save();

        return new InvoiceDraftTarget('legacy-invoice:'.$invoice->id, $invoice->id);
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return list<array{desc: string, qty: string, unit: string, unitPrice: string, vatRate: string, kind: string, productId: int|null}>
     */
    private function legacyLines(array $lines): array
    {
        $mapped = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new LogicException('Quote conversion line is invalid.');
            }
            $description = $line['description'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $unit = $line['unit'] ?? null;
            $unitPrice = $line['unit_price'] ?? null;
            $taxRate = $line['tax_rate'] ?? null;
            $kind = $line['kind'] ?? null;
            $productId = $line['product_id'] ?? null;
            if (! is_string($description) || trim($description) === ''
                || ! is_string($quantity) || ! is_string($unit) || trim($unit) === ''
                || ! is_string($unitPrice) || ! is_string($taxRate)
                || ! is_string($kind) || ! in_array($kind, ['service', 'hardware'], true)
                || ($productId !== null && (! is_int($productId) || $productId < 1))) {
                throw new LogicException('Quote conversion line is invalid.');
            }
            $mapped[] = [
                'desc' => $description,
                'qty' => $quantity,
                'unit' => $unit,
                'unitPrice' => $unitPrice,
                'vatRate' => $taxRate,
                'kind' => $kind,
                'productId' => $productId,
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<array-key, mixed>  $discount
     * @return array{string|null, string|null}
     */
    private function legacyDiscount(array $discount): array
    {
        $type = $discount['type'] ?? null;
        $value = $discount['value'] ?? null;

        return match ($type) {
            'none' => [null, null],
            'percent' => is_string($value)
                ? ['percent', $value]
                : throw new LogicException('Quote conversion discount is invalid.'),
            'fixed' => is_string($value)
                ? ['amount', $value]
                : throw new LogicException('Quote conversion discount is invalid.'),
            default => throw new LogicException('Quote conversion discount is invalid.'),
        };
    }

    private function decimal(int $minor): string
    {
        $negative = $minor < 0;
        $digits = str_pad(ltrim((string) $minor, '-'), 3, '0', STR_PAD_LEFT);
        $decimal = substr($digits, 0, -2).'.'.substr($digits, -2);

        return $negative ? '-'.$decimal : $decimal;
    }
}
