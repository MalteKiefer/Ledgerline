<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

/**
 * Best-effort parser for the company's own historical invoice PDFs (extracted
 * to plain text), spanning several template generations. It recovers the header
 * (number, dates), the customer and the net/VAT/gross totals, detecting the
 * §19 small-business case, and attempts individual line items — reconciling
 * them against the parsed net and falling back to a single summary line.
 *
 * The file name (which encodes number, date and customer) is used as a fallback
 * when the text does not yield a field. The result is always reviewed by a human
 * before saving.
 */
class PdfInvoiceParser
{
    /**
     * @return array{
     *   number: ?string, issue_date: ?string, due_date: ?string, currency: string,
     *   tax_rate: int, small_business: bool,
     *   customer: array{name: ?string, street: ?string, postal_code: ?string, city: ?string, country: string, vat_id: ?string},
     *   lines: list<array{description: string, quantity: float, unit: ?string, unit_price: float, tax_rate: int}>,
     *   net: ?float, tax: ?float, gross: ?float
     * }
     */
    public function parse(string $text, ?string $filename = null): array
    {
        $fromName = $this->fromFilename($filename);

        $smallBusiness = preg_match('/kleinunternehmer|§\s*19/iu', $text) === 1;
        $rate = $smallBusiness ? 0 : (int) ($this->first($text, ['/Steuer\s*\(?\s*(\d{1,2})\s*%/iu', '/(\d{1,2}),00\s*%/']) ?? 19);

        $totals = $this->totals($text, $smallBusiness);
        $customer = $this->customer($text);
        if ($customer['name'] === null) {
            $customer['name'] = $fromName['customer'];
        }

        $lines = $this->lines($text, $rate);
        if ($lines === [] || ! $this->reconciles($lines, $totals['net'])) {
            $lines = $this->summaryLine($totals['net'], $rate);
        }

        return [
            'number' => $this->invoiceNumber($text) ?? $fromName['number'],
            'issue_date' => $this->date($this->first($text, ['/Rechnungsdatum:?\s*(\d{2}\.\d{2}\.\d{4})/iu', '/Datum:?\s*(\d{2}\.\d{2}\.\d{4})/iu'])) ?? $fromName['date'],
            'due_date' => $this->date($this->first($text, ['/F[äa]llig(?:keitsdatum)?\s*(?:am)?:?\s*(\d{2}\.\d{2}\.\d{4})/iu'])),
            'currency' => 'EUR',
            'tax_rate' => $rate,
            'small_business' => $smallBusiness,
            'customer' => $customer,
            'lines' => $lines,
            'net' => $totals['net'],
            'tax' => $totals['tax'],
            'gross' => $totals['gross'],
        ];
    }

    /**
     * The invoice number across the various label styles.
     */
    private function invoiceNumber(string $text): ?string
    {
        $patterns = [
            '/Rechnungsnummer:?\s*([0-9A-Za-z][0-9A-Za-z\-\/]*)/iu',
            '/Rechnungsnr\.?:?\s*([0-9A-Za-z][0-9A-Za-z\-\/]*)/iu',
            '/Rechnung\s*(?:Nr\.?|Nummer|#)\s*:?\s*([0-9A-Za-z][0-9A-Za-z\-\/]*)/iu',
            '/\bRechnung\b\s*:?\s*((?:R-)?\d[0-9A-Za-z\-\/]*)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $candidate = trim($m[1]);
                // A real number contains a digit; reject captured label words.
                if (preg_match('/\d/', $candidate) === 1) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array{net: ?float, tax: ?float, gross: ?float}
     */
    private function totals(string $text, bool $smallBusiness): array
    {
        $net = $this->amountAfter($text, ['Zwischensumme', 'Nettobetrag', 'Nettogesamt', 'Nettosumme']);
        $tax = $this->taxAmount($text);
        $gross = $this->amountAfter($text, ['Rechnungsbetrag', 'Gesamtbetrag', 'Fälliger Betrag', 'Gesamt']);

        // Older "amounts precede labels" layout: N € N € N € Nettobetrag.
        if ($net === null && preg_match('/([\d.,]+)\s*€\s*([\d.,]+)\s*€\s*([\d.,]+)\s*€\s*Nettobetrag/su', $text, $m) === 1) {
            [$net, $tax, $gross] = [$this->number($m[1]), $this->number($m[2]), $this->number($m[3])];
        }

        // §19: no VAT, so net equals gross.
        if ($smallBusiness) {
            $net ??= $gross;
            $tax = 0.0;
            $gross ??= $net;
        }

        if ($gross === null && $net !== null) {
            $gross = $net + ($tax ?? 0.0);
        }
        if ($net === null && $gross !== null) {
            $net = $gross - ($tax ?? 0.0);
        }

        return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
    }

    /**
     * Find the first amount following any of the given labels. Tolerates a colon,
     * a currency symbol/word before or after, a percentage in between, and the
     * value on the next line. Avoids matching "Gesamt" inside "Nettogesamt".
     *
     * @param  list<string>  $labels
     */
    private function amountAfter(string $text, array $labels): ?float
    {
        foreach ($labels as $label) {
            $pattern = '/(?<![A-Za-zäöüÄÖÜ])'.preg_quote($label, '/').'\s*:?\s*(?:€|EUR)?\s*([\d.,]+)\s*(?:€|EUR)?/iu';

            if (preg_match($pattern, $text, $m) === 1) {
                return $this->number($m[1]);
            }
        }

        return null;
    }

    private function taxAmount(string $text): ?float
    {
        // "Umsatzsteuer 19% 7,60", "Steuer (19%): €29,93", "USt. 19% ...".
        if (preg_match('/(?:Umsatzsteuer|Mehrwertsteuer|USt\.?|Steuer)\s*\(?\s*\d{0,2}\s*%?\s*\)?\s*:?\s*(?:€|EUR)?\s*([\d.,]+)/iu', $text, $m) === 1) {
            return $this->number($m[1]);
        }

        return null;
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
            if ($customer['name'] === null && ! str_contains($row, '@') && preg_match('/^\p{L}/u', $row) === 1 && preg_match('/\d{5}/', $row) !== 1) {
                $customer['name'] = $row;
            }
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

        // Template with a date column: desc  dd.mm.yyyy  qty  h  price €rate %amount €
        if (preg_match('/Betrag(.+?)Nettobetrag/su', $text, $block) === 1) {
            preg_match_all('/(.+?)\s+\d{2}\.\d{2}\.\d{4}\s+([\d,]+)\s+h\s+([\d.,]+)\s*€\s*\d+,\d+\s*%\s*([\d.,]+)\s*€/su', $block[1], $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $lines[] = ['description' => $this->clean($row[1]), 'quantity' => $this->number($row[2]) ?? 1.0, 'unit' => 'h', 'unit_price' => $this->number($row[3]) ?? 0.0, 'tax_rate' => $rate];
            }
        }
        if ($lines !== []) {
            return $lines;
        }

        // Modern table: desc  qty  [€]price[€]  [€]amount[€]  (comma or dot, € prefix or suffix)
        if (preg_match('/BESCHREIBUNG.*?BETRAG(.+?)(?:Zwischensumme|RECHNUNGS(?:ÜBERSICHT|ÜBERSICH)|GESAMT)/isu', $text, $block) === 1) {
            preg_match_all('/([\p{L}\[][\s\S]*?)\s+(\d+(?:[.,]\d+)?)\s+€?\s*([\d.,]+)\s*€?\s+€?\s*([\d.,]+)/u', $block[1], $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $description = $this->clean($row[1]);
                $lines[] = ['description' => $description, 'quantity' => $this->number($row[2]) ?? 1.0, 'unit' => $this->detectUnit($description), 'unit_price' => $this->number($row[3]) ?? 0.0, 'tax_rate' => $rate];
            }
        }

        return $lines;
    }

    /**
     * Map a German (or English) unit word found in the text to a unit code that
     * matches the seeded Settings > Units defaults.
     */
    private function detectUnit(string $text, ?string $default = null): ?string
    {
        $map = [
            '/\b(?:stunden?|std\.?|hours?|hrs?)\b/iu' => 'h',
            '/\b(?:werktage?|tage?n?)\b/iu' => 'day',
            '/\b(?:st(?:ü|ue)ck|stk\.?|pcs|pieces?)\b/iu' => 'pcs',
            '/pauschal/iu' => 'flat',
            '/\b(?:kilometer|km)\b/iu' => 'km',
            '/\b(?:monate?n?|months?)\b/iu' => 'month',
            '/\b(?:lizenz(?:en)?|licen[cs]es?)\b/iu' => 'lic',
        ];

        foreach ($map as $pattern => $code) {
            if (preg_match($pattern, $text) === 1) {
                return $code;
            }
        }

        return $default;
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
        return [['description' => 'Imported invoice', 'quantity' => 1.0, 'unit' => null, 'unit_price' => $net ?? 0.0, 'tax_rate' => $rate]];
    }

    /**
     * Fallback fields derived from the file name, e.g.
     * "20190114_ Kiefer Networks_ Rechnung R-00072 - STN Nürnberg.pdf".
     *
     * @return array{number: ?string, date: ?string, customer: ?string}
     */
    private function fromFilename(?string $filename): array
    {
        $out = ['number' => null, 'date' => null, 'customer' => null];

        if ($filename === null) {
            return $out;
        }

        $name = preg_replace('/\.pdf$/i', '', $filename) ?? $filename;

        if (preg_match('/(\d{4})(\d{2})(\d{2})/', $name, $m) === 1) {
            $out['date'] = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        if (preg_match('/Rechnung[_\s]*(?:Nr\.?|nr\.?)?[_\s]*((?:R-)?\d[0-9A-Za-z\-]*)/u', $name, $m) === 1) {
            $out['number'] = $m[1];
        }

        // Customer usually follows a " - " separator.
        if (preg_match('/\s-\s(.+)$/u', $name, $m) === 1) {
            $out['customer'] = trim(preg_replace('/\s+\d+$/', '', $m[1]) ?? $m[1]);
        }

        return $out;
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

    /**
     * Parse a German- or English-formatted amount to a float.
     */
    private function number(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $s = preg_replace('/[^\d.,]/', '', $value) ?? '';
        if ($s === '') {
            return null;
        }

        $hasComma = str_contains($s, ',');
        $hasDot = str_contains($s, '.');

        if ($hasComma && $hasDot) {
            // German: dot = thousands, comma = decimal.
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif ($hasComma) {
            $s = str_replace(',', '.', $s);
        } elseif ($hasDot) {
            // Dot only: decimal if the last group has 2 digits, else thousands.
            $parts = explode('.', $s);
            $last = end($parts);
            if (count($parts) > 2 || mb_strlen($last) === 3) {
                $s = str_replace('.', '', $s);
            }
        }

        return (float) $s;
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
