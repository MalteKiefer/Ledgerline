<?php

declare(strict_types=1);

namespace App\Services\Invoices;

/**
 * Extracts table rows from Ledgerline's historic outgoing-invoice PDF layouts.
 *
 * The importer used to collapse every table into one aggregate row. This parser
 * deliberately produces a result only when every recognised row carries a
 * cent-exact amount; the repair command then independently checks the sum
 * against the invoice net total before it may write anything.
 */
final class HistoricInvoicePdfParser
{
    /**
     * @return list<array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, amount: float}>
     */
    public function lines(string $text, float $vatRate): array
    {
        $rows = [];
        $current = null;
        $inTable = false;
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            if ($this->isTableHeader($line)) {
                $inTable = true;

                continue;
            }
            if (! $inTable) {
                continue;
            }
            if ($this->isSummary($line)) {
                break;
            }
            $modern = $this->modernRow($line, $vatRate);
            $position = $modern === null ? $this->positionRow($line, $vatRate) : null;
            $legacy = $modern === null && $position === null ? $this->legacyRow($line, $vatRate) : null;
            $row = $modern ?? $position ?? $legacy;
            if ($row !== null) {
                if ($current !== null) {
                    $rows[] = $current;
                }
                $current = $row;

                continue;
            }
            if ($current !== null) {
                $current['desc'] .= ' '.$line;
            }
        }
        if ($current !== null) {
            $rows[] = $current;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => $row['desc'] !== ''));
    }

    /** @return array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, amount: float}|null */
    private function modernRow(string $line, float $fallbackVatRate): ?array
    {
        $pattern = '/^(.*?)\s+\d{2}\.\d{2}\.\d{4}\s+([0-9.,]+)\s+(\S+)\s+([0-9.,]+)\s*€?\s+([0-9.,]+)\s*%\s+([0-9.,]+)\s*€?$/u';
        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return $this->row($matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6], $fallbackVatRate);
    }

    /** @return array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, amount: float}|null */
    private function legacyRow(string $line, float $fallbackVatRate): ?array
    {
        $pattern = '/^([0-9.,]+)\s+([[:alpha:].]+)\s+(.+?)\s+([0-9.,]+)(?:\s+\d+)?\s+([0-9.,]+)$/u';
        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return $this->row($matches[3], $matches[1], $matches[2], $matches[4], (string) $fallbackVatRate, $matches[5], $fallbackVatRate);
    }

    /** @return array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, amount: float}|null */
    private function positionRow(string $line, float $fallbackVatRate): ?array
    {
        $pattern = '/^\d+\s+(.+?)\s+([0-9.,]+)\s*€\s+([0-9.,]+)\s+([[:alpha:].]+)\s+([0-9.,]+)\s*€$/u';
        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return $this->row($matches[1], $matches[3], $matches[4], $matches[2], (string) $fallbackVatRate, $matches[5], $fallbackVatRate);
    }

    /** @return array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, amount: float}|null */
    private function row(string $desc, string $qty, string $unit, string $price, string $vatRate, string $amount, float $fallbackVatRate): ?array
    {
        $parsedQty = $this->number($qty);
        $parsedPrice = $this->number($price);
        $parsedVatRate = $this->number($vatRate);
        $parsedAmount = $this->number($amount);
        $desc = trim(preg_replace('/\s+/u', ' ', $desc) ?? '');
        if ($desc === '' || $parsedQty === null || $parsedPrice === null || $parsedAmount === null) {
            return null;
        }

        return [
            'desc' => $desc,
            'qty' => $parsedQty,
            'unit' => trim($unit),
            'unitPrice' => $parsedPrice,
            'vatRate' => $parsedVatRate ?? $fallbackVatRate,
            'amount' => $parsedAmount,
        ];
    }

    private function number(string $value): ?float
    {
        $value = str_replace(['.', ',', '€', ' '], ['', '.', '', ''], trim($value));

        return is_numeric($value) ? (float) $value : null;
    }

    private function isSummary(string $line): bool
    {
        return preg_match('/^(netto|nettobetrag|umsatzsteuer|ust\.?|total|gesamt)/iu', $line) === 1;
    }

    private function isTableHeader(string $line): bool
    {
        return preg_match('/beschreibung.*(?:datum|preis).*?(?:menge|steuern|einheit)/iu', $line) === 1
            || preg_match('/^pos\s+beschreibung.*einzelpreis.*(?:anzahl|menge)/iu', $line) === 1;
    }
}
