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
            'lines' => $lines,
            'discount_type' => $discount['type'] ?? null,
            'discount_value' => $discount['value'] ?? null,
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

    private function decimal(int $minor): string
    {
        $negative = $minor < 0;
        $digits = str_pad(ltrim((string) $minor, '-'), 3, '0', STR_PAD_LEFT);
        $decimal = substr($digits, 0, -2).'.'.substr($digits, -2);

        return $negative ? '-'.$decimal : $decimal;
    }
}
