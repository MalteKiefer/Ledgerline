<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final readonly class RecurringTemplateVersionData
{
    /** @var array<string, mixed> */
    public array $snapshot;

    public string $snapshotSha256;

    public function __construct(
        public DateTimeImmutable $effectiveFrom,
        public InvoiceDraftData $draft,
    ) {
        $lines = [];
        $snapshotLines = [];

        foreach ($draft->lines as $line) {
            $quantity = DecimalQuantity::fromString($line->quantity);
            $lines[] = new DocumentLine(
                $line->description,
                $quantity,
                Money::fromMinor($line->unitPriceMinor, $draft->currency),
                $line->taxRateBasisPoints,
            );
            $snapshotLines[] = [
                'description' => $line->description,
                'kind' => $line->kind,
                'product_id' => $line->productId,
                'quantity' => $line->quantity,
                'quantity_scaled' => $quantity->scaled(),
                'tax_rate_basis_points' => $line->taxRateBasisPoints,
                'unit' => $line->unit,
                'unit_price_minor' => $line->unitPriceMinor,
            ];
        }

        $totals = (new DocumentCalculator)->calculate($lines, $draft->discount);
        if (($draft->controlNetMinor !== null && $draft->controlNetMinor !== $totals->net->minor())
            || ($draft->controlVatMinor !== null && $draft->controlVatMinor !== $totals->vat->minor())
            || ($draft->controlGrossMinor !== null && $draft->controlGrossMinor !== $totals->gross->minor())) {
            throw new DomainException('document_totals_mismatch');
        }

        $this->snapshot = $this->canonicalObject([
            'currency' => $draft->currency,
            'customer' => $draft->customer,
            'discount' => [
                'basis_points' => $draft->discount->basisPoints(),
                'currency' => $draft->discount->currency(),
                'fixed_minor' => $draft->discount->fixedMinor(),
            ],
            'due_date' => $draft->dueDate->format('Y-m-d'),
            'issue_date' => $draft->issueDate->format('Y-m-d'),
            'lines' => $snapshotLines,
            'partner_id' => $draft->partnerId,
            'project_id' => $draft->projectId,
            'totals' => [
                'currency' => $draft->currency,
                'discount_minor' => $totals->discount->minor(),
                'gross_minor' => $totals->gross->minor(),
                'net_minor' => $totals->net->minor(),
                'tax_breakdowns' => array_map(
                    static fn ($breakdown): array => [
                        'gross_minor' => $breakdown->gross->minor(),
                        'net_minor' => $breakdown->net->minor(),
                        'tax_rate_basis_points' => $breakdown->taxRateBasisPoints,
                        'vat_minor' => $breakdown->vat->minor(),
                    ],
                    $totals->taxBreakdowns,
                ),
                'vat_minor' => $totals->vat->minor(),
            ],
        ]);
        $this->snapshotSha256 = hash(
            'sha256',
            json_encode($this->snapshot, JSON_THROW_ON_ERROR),
        );
    }

    public function effectiveLocalDate(): string
    {
        return $this->effectiveFrom->format('Y-m-d');
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            if (is_float($item)
                || (! is_array($item) && ! is_string($item) && ! is_int($item) && ! is_bool($item) && $item !== null)) {
                throw new InvalidArgumentException('Recurring invoice snapshots require exact JSON values without floats.');
            }

            $canonical[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function canonicalObject(array $value): array
    {
        $canonical = $this->canonicalize($value);
        $object = [];

        foreach ($canonical as $key => $item) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Recurring invoice snapshots require an object root.');
            }
            $object[$key] = $item;
        }

        return $object;
    }
}
