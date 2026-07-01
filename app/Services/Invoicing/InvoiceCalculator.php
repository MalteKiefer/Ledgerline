<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\TaxMode;

/**
 * Computes per-line and invoice totals in integer cents.
 *
 * Unit prices are net. Each line's net and tax are self-consistent
 * (tax = net * rate). An invoice-level discount reduces the net total and the
 * tax is scaled proportionally. Reverse-charge and small-business modes apply
 * 0% tax.
 */
class InvoiceCalculator
{
    /**
     * @param  list<array{quantity: float|string, unit_price_cents: int, tax_rate: int}>  $lines
     * @return array{lines: list<array{line_net_cents: int, line_tax_cents: int}>, net_cents: int, tax_cents: int, gross_cents: int, discount_cents: int}
     */
    public function compute(array $lines, TaxMode $mode, int $discountCents): array
    {
        $chargesTax = $mode->chargesTax();

        $computed = [];
        $subtotalNet = 0;
        $subtotalTax = 0;

        foreach ($lines as $line) {
            $net = (int) round(((float) $line['quantity']) * $line['unit_price_cents']);
            $tax = $chargesTax ? (int) round($net * $line['tax_rate'] / 100) : 0;

            $computed[] = ['line_net_cents' => $net, 'line_tax_cents' => $tax];
            $subtotalNet += $net;
            $subtotalTax += $tax;
        }

        $discount = max(0, min($discountCents, $subtotalNet));
        $net = $subtotalNet - $discount;
        $tax = $subtotalNet > 0 ? (int) round($subtotalTax * $net / $subtotalNet) : 0;

        return [
            'lines' => $computed,
            'net_cents' => $net,
            'tax_cents' => $tax,
            'gross_cents' => $net + $tax,
            'discount_cents' => $discount,
        ];
    }
}
