<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands;

use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use InvalidArgumentException;

final readonly class CreateDocumentRevision
{
    public function __construct(
        private DocumentRevisionRepository $revisions,
        private DocumentCalculator $calculator,
    ) {}

    public function handle(CreateRevisionData $data): DocumentRevisionId
    {
        $totals = $this->calculator->calculate($data->lines, $data->discount);
        $canonicalSnapshot = $this->canonicalize($this->authoritativeSnapshot($data, $totals));
        $canonicalJson = json_encode($canonicalSnapshot, JSON_THROW_ON_ERROR);

        return $this->revisions->create(
            $data,
            $totals,
            $canonicalSnapshot,
            hash('sha256', $canonicalJson),
        );
    }

    /** @return array<array-key, mixed> */
    private function authoritativeSnapshot(CreateRevisionData $data, DocumentTotals $totals): array
    {
        $snapshot = $data->snapshot;
        $snapshot['lines'] = array_map(
            static fn ($line): array => [
                'description' => $line->description,
                'quantity_scaled' => $line->quantity->scaled(),
                'unit_price_minor' => $line->unitPrice->minor(),
                'currency' => $line->unitPrice->currency(),
                'tax_rate_basis_points' => $line->taxRateBasisPoints,
            ],
            $data->lines,
        );
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

        return $snapshot;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
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
