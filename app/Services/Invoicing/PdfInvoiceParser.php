<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

/**
 * Best-effort parser for the company's own historical invoice PDFs (extracted
 * to plain text). It reliably recovers the header (number, dates), the customer
 * and the net/VAT/gross totals, and attempts individual line items — falling
 * back to a single summary line when they cannot be reconciled.
 *
 * The result is always reviewed and corrected by a human before saving.
 */
class PdfInvoiceParser
{
    /**
     * @return array{
     *   number: ?string, issue_date: ?string, due_date: ?string, currency: string,
     *   tax_rate: int, customer: array{name: ?string, street: ?string, postal_code: ?string, city: ?string, country: string, vat_id: ?string},
     *   lines: list<array{description: string, quantity: float, unit: ?string, unit_price: float, tax_rate: int}>,
     *   net: ?float, tax: ?float, gross: ?float
     * }
     */
    public function parse(string $text): array
    {
        $rate = (int) ($this->first($text, ['/Steuer\s*\((\d+)\s*%\)/', '/(\d+),00\s*%/']) ?? 19);

        $totals = $this->totals($text);
        $customer = $this->customer($text);

        $lines = $this->lines($text, $rate);
        if ($lines === [] || ! $this->reconciles($lines, $totals['net'])) {
            $lines = $this->summaryLine($totals['net'], $rate);
        }

        return [
            'number' => $this->first($text, ['/Rechnung\s*Nr\.?:?\s*([0-9A-Za-z\-\/]+)/', '/Rechnungsnr\.?:?\s*([0-9A-Za-z\-\/]+)/']),
            'issue_date' => $this->date($this->first($text, ['/Rechnungsdatum:?\s*(\d{2}\.\d{2}\.\d{4})/', '/Datum:?\s*(\d{2}\.\d{2}\.\d{4})/'])),
            'due_date' => $this->date($this->first($text, ['/F[äa]llig\s*am:?\s*(\d{2}\.\d{2}\.\d{4})/u'])),
            'currency' => 'EUR',
            'tax_rate' => $rate,
            'customer' => $customer,
            'lines' => $lines,
            'net' => $totals['net'],
            'tax' => $totals['tax'],
            'gross' => $totals['gross'],
        ];
    }

    /**
     * @return array{net: ?float, tax: ?float, gross: ?float}
     */
    private function totals(string $text): array
    {
        // Newer template: labelled amounts.
        $net = $this->number($this->first($text, ['/Zwischensumme\s*([\d.,]+)\s*€/', '/Nettobetrag\D{0,4}([\d.,]+)/']));
        $tax = $this->number($this->first($text, ['/Steuer\s*\(\d+\s*%\)\s*([\d.,]+)\s*€/']));
        $gross = $this->number($this->first($text, ['/Gesamt\s*([\d.,]+)\s*€/']));

        // Older template: three amounts precede the "Nettobetrag / USt. / Gesamt" labels.
        if ($net === null && preg_match('/([\d.,]+)\s*€\s*([\d.,]+)\s*€\s*([\d.,]+)\s*€\s*Nettobetrag/su', $text, $m) === 1) {
            $net = $this->number($m[1]);
            $tax = $this->number($m[2]);
            $gross = $this->number($m[3]);
        }

        if ($gross === null && $net !== null && $tax !== null) {
            $gross = $net + $tax;
        }

        return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
    }

    /**
     * @return array{name: ?string, street: ?string, postal_code: ?string, city: ?string, country: string, vat_id: ?string}
     */
    private function customer(string $text): array
    {
        $block = null;

        if (preg_match('/RECHNUNG AN\s*(.+?)(?:RECHNUNGSDETAILS|LEISTUNG)/su', $text, $m) === 1) {
            $block = $m[1];
        } elseif (preg_match('/Neudrossenfeld\s*(.+?)Beschreibung/su', $text, $m) === 1) {
            $block = $m[1];
        }

        $customer = ['name' => null, 'street' => null, 'postal_code' => null, 'city' => null, 'country' => 'DE', 'vat_id' => null];

        if ($block === null) {
            return $customer;
        }

        $rows = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $block) ?: [])));

        foreach ($rows as $row) {
            if ($customer['name'] === null && ! str_contains($row, '@') && preg_match('/^\p{L}/u', $row) === 1) {
                $customer['name'] = $row;
            }
            // "Street 1, 12345 City" on one line.
            if (preg_match('/^(\p{L}.+?\s\d+[a-z]?),\s*(\d{5})\s+(\p{L}.+)$/u', $row, $m) === 1) {
                $customer['street'] = trim($m[1]);
                $customer['postal_code'] = $m[2];
                $customer['city'] = trim(explode(',', $m[3])[0]);
            } elseif (preg_match('/(\d{5})\s+(\p{L}.+)$/u', $row, $m) === 1) {
                $customer['postal_code'] = $m[1];
                $customer['city'] = trim(explode(',', $m[2])[0]);
            } elseif ($customer['street'] === null && preg_match('/^(\p{L}.*\s\d+[a-z]?)$/u', $row, $m) === 1) {
                $customer['street'] = trim($m[1]);
            }
            if (preg_match('/USt-?IdNr\.?:?\s*([A-Z]{2}\s?[\d\s]+)/u', $row, $m) === 1) {
                $customer['vat_id'] = preg_replace('/\s+/', '', $m[1]);
            }
        }

        return $customer;
    }

    /**
     * @return list<array{description: string, quantity: float, unit: ?string, unit_price: float, tax_rate: int}>
     */
    private function lines(string $text, int $rate): array
    {
        $lines = [];

        // Older template: within "Betrag ... Nettobetrag", rows are
        // "desc  dd.mm.yyyy  qty  h  price €rate %amount €".
        if (preg_match('/Betrag(.+?)Nettobetrag/su', $text, $block) === 1) {
            preg_match_all('/(.+?)\s+\d{2}\.\d{2}\.\d{4}\s+([\d,]+)\s+h\s+([\d.,]+)\s*€\s*\d+,\d+\s*%\s*([\d.,]+)\s*€/su', $block[1], $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $lines[] = [
                    'description' => $this->clean($row[1]),
                    'quantity' => $this->number($row[2]) ?? 1.0,
                    'unit' => 'h',
                    'unit_price' => $this->number($row[3]) ?? 0.0,
                    'tax_rate' => $rate,
                ];
            }
        }

        if ($lines !== []) {
            return $lines;
        }

        // Newer template: within "BESCHREIBUNG ... BETRAG ... Zwischensumme",
        // rows are "desc  qty  price €  amount €".
        if (preg_match('/BESCHREIBUNG.*?BETRAG(.+?)Zwischensumme/su', $text, $block) === 1) {
            preg_match_all('/([\s\S]+?)\s+(\d+(?:,\d+)?)\s+([\d.,]+)\s*€\s+([\d.,]+)\s*€/u', $block[1], $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $lines[] = [
                    'description' => $this->clean($row[1]),
                    'quantity' => $this->number($row[2]) ?? 1.0,
                    'unit' => null,
                    'unit_price' => $this->number($row[3]) ?? 0.0,
                    'tax_rate' => $rate,
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  list<array{quantity: float, unit_price: float}>  $lines
     */
    private function reconciles(array $lines, ?float $net): bool
    {
        if ($net === null) {
            return false;
        }

        $sum = array_sum(array_map(fn (array $l): float => $l['quantity'] * $l['unit_price'], $lines));

        return abs($sum - $net) <= 0.5;
    }

    /**
     * @return list<array{description: string, quantity: float, unit: ?string, unit_price: float, tax_rate: int}>
     */
    private function summaryLine(?float $net, int $rate): array
    {
        return [[
            'description' => 'Imported invoice',
            'quantity' => 1.0,
            'unit' => null,
            'unit_price' => $net ?? 0.0,
            'tax_rate' => $rate,
        ]];
    }

    /**
     * @param  list<string>  $patterns
     */
    private function first(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return trim($m[1]);
            }
        }

        return null;
    }

    private function number(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^\d.,]/', '', $value) ?? '';
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        return $clean === '' ? null : (float) $clean;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $value, $m) !== 1) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return mb_substr(trim($value), 0, 255);
    }
}
