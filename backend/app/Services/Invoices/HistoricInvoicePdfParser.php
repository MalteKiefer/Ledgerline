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
        $pendingDescription = [];
        $inTable = false;
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '') {
                if ($inTable && $pendingDescription !== [] && end($pendingDescription) !== '') {
                    $pendingDescription[] = '';
                }

                continue;
            }
            if ($this->isTableHeader($line)) {
                $inTable = true;
                $pendingDescription = [];

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
            $trailing = $modern === null && $position === null && $legacy === null ? $this->trailingQuantityRow($line, $vatRate) : null;
            $direct = $modern ?? $position ?? $legacy ?? $trailing;
            $continuation = '';
            if ($direct === null) {
                [$continuation, $description] = $this->splitPendingDescription($pendingDescription);
                $row = $this->pendingDescriptionRow($line, $description, $vatRate);
            } else {
                $row = $direct;
            }
            if ($row !== null) {
                if ($current !== null) {
                    $extra = $direct === null ? $continuation : $this->pendingText($pendingDescription);
                    if ($extra !== '') {
                        $current['desc'] .= ' '.$extra;
                    }
                    $rows[] = $current;
                }
                $current = $row;
                $pendingDescription = [];

                continue;
            }
            $pendingDescription[] = $line;
        }
        if ($current !== null) {
            $extra = $this->pendingText($pendingDescription);
            if ($extra !== '') {
                $current['desc'] .= ' '.$extra;
            }
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

    /** Extract rows whose description ends before the quantity/unit/price columns. */
    private function trailingQuantityRow(string $line, float $fallbackVatRate): ?array
    {
        $pattern = '/^(.*?)\s+([0-9.,]+)\s+([[:alpha:].]+)\s+([0-9.,]+)\s*€?\s+([0-9.,]+)\s*€?$/u';
        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return $this->row($matches[1], $matches[2], $matches[3], $matches[4], (string) $fallbackVatRate, $matches[5], $fallbackVatRate);
    }

    /**
     * Extract the newer layout where a wrapped description is printed above a
     * following "quantity, unit price, amount" line.
     *
     * @return array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, amount: float}|null
     */
    private function pendingDescriptionRow(string $line, string $description, float $fallbackVatRate): ?array
    {
        if ($description === '') {
            return null;
        }
        $pattern = '/^([0-9.,]+)\s+€?\s*([0-9.,]+)\s*€?\s+€?\s*([0-9.,]+)\s*€?$/u';
        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return $this->row($description, $matches[1], 'h', $matches[2], (string) $fallbackVatRate, $matches[3], $fallbackVatRate);
    }

    /** @param list<string> $pendingDescription @return array{string, string} */
    private function splitPendingDescription(array $pendingDescription): array
    {
        $blocks = [];
        $block = [];
        foreach ($pendingDescription as $line) {
            if ($line === '') {
                if ($block !== []) {
                    $blocks[] = trim(implode(' ', $block));
                    $block = [];
                }

                continue;
            }
            $block[] = $line;
        }
        if ($block !== []) {
            $blocks[] = trim(implode(' ', $block));
        }
        $blocks = array_values(array_filter($blocks));
        if ($blocks === []) {
            return ['', ''];
        }

        return [implode(' ', array_slice($blocks, 0, -1)), $blocks[array_key_last($blocks)]];
    }

    /** @param list<string> $pendingDescription */
    private function pendingText(array $pendingDescription): string
    {
        return trim(implode(' ', array_filter($pendingDescription, static fn (string $line): bool => $line !== '')));
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
        return preg_match('/^(netto|nettobetrag|zwischensumme|umsatzsteuer|ust\.?|zu zahlen|total|gesamt)/iu', $line) === 1;
    }

    private function isTableHeader(string $line): bool
    {
        return preg_match('/beschreibung.*(?:datum|preis).*?(?:menge|steuern|einheit)/iu', $line) === 1
            || preg_match('/^pos\s+beschreibung.*einzelpreis.*(?:anzahl|menge)/iu', $line) === 1
            || preg_match('/^(?:pos\s+)?beschreibung.*(?:menge|anzahl|einheit).*?(?:einzelpreis|preis)/iu', $line) === 1;
    }
}
