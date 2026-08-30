<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Services;

use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use InvalidArgumentException;

final readonly class CanonicalDocumentSnapshot
{
    /** @return array<array-key, mixed> */
    public function build(CreateRevisionData $data, DocumentTotals $totals): array
    {
        $snapshot = $data->snapshot;
        $sourceLines = $snapshot['lines'] ?? null;
        $quoteLines = [];
        foreach ($data->lines as $index => $line) {
            $canonicalLine = [
                'description' => $line->description,
                'quantity_scaled' => $line->quantity->scaled(),
                'unit_price_minor' => $line->unitPrice->minor(),
                'currency' => $line->unitPrice->currency(),
                'tax_rate_basis_points' => $line->taxRateBasisPoints,
            ];
            if (($snapshot['document_type'] ?? null) === 'quote') {
                $sourceLine = is_array($sourceLines) ? ($sourceLines[$index] ?? null) : null;
                if (! is_array($sourceLine)) {
                    throw new InvalidArgumentException('Quote snapshot line metadata is missing.');
                }
                $canonicalLine = [
                    ...$canonicalLine,
                    ...$this->quoteLineMetadata($sourceLine),
                ];
            }
            $quoteLines[] = $canonicalLine;
        }
        $snapshot['lines'] = $quoteLines;
        $snapshot['totals'] = [
            'net_minor' => $totals->net->minor(),
            'vat_minor' => $totals->vat->minor(),
            'gross_minor' => $totals->gross->minor(),
            'discount_minor' => $totals->discount->minor(),
            'currency' => $totals->net->currency(),
            'tax_breakdowns' => array_map(
                static fn ($breakdown): array => [
                    'tax_rate_basis_points' => $breakdown->taxRateBasisPoints,
                    'net_minor' => $breakdown->net->minor(),
                    'vat_minor' => $breakdown->vat->minor(),
                    'gross_minor' => $breakdown->gross->minor(),
                ],
                $totals->taxBreakdowns,
            ),
        ];

        return $this->canonicalize($snapshot);
    }

    /**
     * @param  array<array-key, mixed>  $line
     * @return array{quantity: string, unit: string, unit_price: string, tax_rate: string, kind: string, product_id: int|null}
     */
    private function quoteLineMetadata(array $line): array
    {
        foreach (['quantity', 'unit', 'unit_price', 'tax_rate', 'kind'] as $key) {
            if (! isset($line[$key]) || ! is_string($line[$key]) || trim($line[$key]) === '') {
                throw new InvalidArgumentException("Quote snapshot line {$key} is missing.");
            }
        }
        $productId = $line['product_id'] ?? null;
        if ($productId !== null && (! is_int($productId) || $productId < 1)) {
            throw new InvalidArgumentException('Quote snapshot product ID must be positive or null.');
        }

        return [
            'quantity' => $line['quantity'],
            'unit' => $line['unit'],
            'unit_price' => $line['unit_price'],
            'tax_rate' => $line['tax_rate'],
            'kind' => $line['kind'],
            'product_id' => $productId,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    public function canonicalize(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            if (is_float($item)) {
                throw new InvalidArgumentException('Document snapshots cannot contain floating-point values.');
            }

            if (! is_array($item)
                && ! is_string($item)
                && ! is_int($item)
                && ! is_bool($item)
                && $item !== null) {
                throw new InvalidArgumentException(
                    'Document snapshots may contain only arrays and scalar JSON values.',
                );
            }

            $canonical[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }
}
