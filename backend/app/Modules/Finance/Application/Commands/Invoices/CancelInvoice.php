<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\CancelInvoiceData;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;

final readonly class CancelInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
        private FinalizeInvoice $finalize,
        private Clock $clock,
    ) {}

    public function handle(CancelInvoiceData $data, IdempotencyKey $key): FinalizedInvoice
    {
        $cancellationId = $this->invoices->createCancellationDraft(
            $data->invoiceId,
            $key,
            fn (InvoiceView $original, int $revisionId, string $snapshotSha256): InvoiceDraftSource => $this->source($original, $revisionId, $snapshotSha256),
        );

        return $this->finalize->handle(
            $cancellationId,
            new IdempotencyKey('invoice.cancel.finalize.'.$data->invoiceId->value),
        );
    }

    private function source(
        InvoiceView $original,
        int $revisionId,
        string $snapshotSha256,
    ): InvoiceDraftSource {
        $snapshot = $original->snapshot;
        $customer = $snapshot['customer'] ?? null;
        $sourceLines = $snapshot['lines'] ?? null;
        $discount = $snapshot['discount'] ?? null;
        if (! is_array($customer) || ! is_array($sourceLines) || $sourceLines === [] || ! is_array($discount)) {
            throw new DomainException('cancellation_snapshot_invalid');
        }
        $customerData = [];
        foreach ($customer as $key => $value) {
            if (! is_string($key)) {
                throw new DomainException('cancellation_snapshot_invalid');
            }
            $customerData[$key] = $value;
        }

        $lines = [];
        foreach ($sourceLines as $line) {
            if (! is_array($line)
                || ! is_string($line['description'] ?? null)
                || ! is_string($line['quantity'] ?? null)
                || ! is_int($line['unit_price_minor'] ?? null)
                || ! is_int($line['tax_rate_basis_points'] ?? null)
                || ! is_string($line['unit'] ?? null)) {
                throw new DomainException('cancellation_snapshot_invalid');
            }
            $productId = $line['product_id'] ?? null;
            $kind = $line['kind'] ?? null;
            if (($productId !== null && ! is_int($productId)) || ($kind !== null && ! is_string($kind))) {
                throw new DomainException('cancellation_snapshot_invalid');
            }
            $lines[] = new InvoiceLineData(
                $line['description'],
                $this->negatedQuantity($line['quantity']),
                $line['unit_price_minor'],
                $line['tax_rate_basis_points'],
                $line['unit'],
                $productId,
                $kind,
            );
        }

        $basisPoints = $discount['basis_points'] ?? null;
        $fixedMinor = $discount['fixed_minor'] ?? null;
        if (! is_int($basisPoints) || ! is_int($fixedMinor) || ($basisPoints !== 0 && $fixedMinor !== 0)) {
            throw new DomainException('cancellation_snapshot_invalid');
        }
        $reversedDiscount = $basisPoints !== 0
            ? Discount::percentBasisPoints($basisPoints, $original->currency)
            : ($fixedMinor !== 0
                ? Discount::fixed(Money::fromMinor(-$fixedMinor, $original->currency))
                : Discount::none($original->currency));
        $today = new DateTimeImmutable($this->clock->now()->format('Y-m-d'));

        return new InvoiceDraftSource(
            'cancellation',
            $original->uuid,
            $revisionId,
            $snapshotSha256,
            new InvoiceDraftData(
                issueDate: $today,
                dueDate: $today,
                currency: $original->currency,
                customer: $customerData,
                lines: $lines,
                discount: $reversedDiscount,
                partnerId: $original->partnerId,
                projectId: $original->projectId,
            ),
        );
    }

    private function negatedQuantity(string $quantity): string
    {
        if ($quantity === '0.0000' || $quantity === '-0.0000') {
            return '0.0000';
        }

        return str_starts_with($quantity, '-') ? substr($quantity, 1) : '-'.$quantity;
    }
}
