<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\InvoiceType;
use App\Enums\TaxMode;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Unit;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;

/**
 * Builds a ZUGFeRD / Factur-X (EN16931 profile) document from an invoice.
 *
 * All amounts are converted from integer cents to major units. Tax categories:
 * S = standard, AE = reverse charge, E = exempt (§19 small business).
 */
class ZugferdInvoiceBuilder
{
    public function build(Invoice $invoice, CompanyProfile $company): ZugferdDocumentBuilder
    {
        $mode = $invoice->tax_mode;
        [$category, $exemptReason] = $this->category($mode);

        $doc = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

        $doc->setDocumentInformation(
            $invoice->number ?? (string) $invoice->id,
            $invoice->type === InvoiceType::CREDIT_NOTE ? '381' : '380',
            $invoice->issue_date,
            $invoice->currency,
        );

        $doc->setDocumentSeller($company->legal_name);
        if (filled($company->vat_id)) {
            $doc->addDocumentSellerTaxRegistration('VA', $company->vat_id);
        }
        if (filled($company->tax_number)) {
            $doc->addDocumentSellerTaxRegistration('FC', $company->tax_number);
        }
        $doc->setDocumentSellerAddress(
            $company->address_line1,
            $company->address_line2,
            null,
            $company->postal_code,
            $company->city,
            $company->country ?? 'DE',
        );

        $customer = $invoice->customer;
        $doc->setDocumentBuyer($customer?->name ?? 'Customer');
        $doc->setDocumentBuyerAddress(
            $customer?->street,
            null,
            null,
            $customer?->postal_code,
            $customer?->city,
            $customer?->country ?? 'DE',
        );

        // Positions.
        $position = 0;
        foreach ($invoice->lines as $line) {
            $position++;
            $rate = $mode->chargesTax() ? (float) $line->tax_rate : 0.0;

            $doc->addNewPosition((string) $position)
                ->setDocumentPositionProductDetails($line->description !== '' ? $line->description : 'Item')
                ->setDocumentPositionNetPrice($line->unit_price_cents / 100)
                ->setDocumentPositionQuantity((float) $line->quantity, Unit::zugferdCodeFor($line->unit))
                ->addDocumentPositionTax($category, 'VAT', $rate)
                ->setDocumentPositionLineSummation($line->line_net_cents / 100);
        }

        // Document-level tax breakdown (per distinct rate, discount applied pro rata).
        $this->addTaxBreakdown($doc, $invoice, $category, $exemptReason);

        $subtotal = ($invoice->net_cents + $invoice->discount_cents) / 100;
        $doc->setDocumentSummation(
            $invoice->gross_cents / 100,      // grand total
            $invoice->gross_cents / 100,      // due payable
            $subtotal,                        // line total (sum of line nets)
            0.0,                              // charges
            $invoice->discount_cents / 100,   // allowances (discount)
            $invoice->net_cents / 100,        // tax basis total
            $invoice->tax_cents / 100,        // tax total
        );

        if ($invoice->due_date !== null) {
            $doc->addDocumentPaymentTerm(null, $invoice->due_date);
        }

        return $doc;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function category(TaxMode $mode): array
    {
        return match ($mode) {
            TaxMode::STANDARD => ['S', null],
            TaxMode::REVERSE_CHARGE => ['AE', 'Reverse charge'],
            TaxMode::SMALL_BUSINESS => ['E', 'Kleinunternehmer §19 UStG'],
        };
    }

    private function addTaxBreakdown(ZugferdDocumentBuilder $doc, Invoice $invoice, string $category, ?string $exemptReason): void
    {
        if (! $invoice->tax_mode->chargesTax()) {
            $doc->addDocumentTax($category, 'VAT', $invoice->net_cents / 100, 0.0, 0.0, $exemptReason);

            return;
        }

        $subtotal = max(1, $invoice->net_cents + $invoice->discount_cents);
        $ratio = ($invoice->net_cents) / $subtotal; // discount factor

        $byRate = [];
        foreach ($invoice->lines as $line) {
            $byRate[$line->tax_rate] = ($byRate[$line->tax_rate] ?? 0) + $line->line_net_cents;
        }

        foreach ($byRate as $rate => $basisPre) {
            $basis = (int) round($basisPre * $ratio);
            $tax = (int) round($basis * $rate / 100);
            $doc->addDocumentTax('S', 'VAT', $basis / 100, $tax / 100, (float) $rate);
        }
    }
}
