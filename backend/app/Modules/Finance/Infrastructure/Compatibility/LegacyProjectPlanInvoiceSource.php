<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraftFromSource;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;

/**
 * Bills grouped project time from the older `FinanceProject`/`FinanceTimeEntry`
 * plan screen (`FinanceProjectPlanController::invoiceTime`, distinct from the
 * newer hexagonal Projects module and its own `LegacyProjectTimeInvoiceSource`)
 * into a finance-v2 invoice draft, instead of writing a legacy `invoices` row.
 *
 * The controller owns entry selection, rate grouping, and stamping invoiced
 * entries with the result inside its own transaction; this class only builds
 * the frozen source snapshot (`sourceType='project_time_batch'`) and calls
 * `CreateInvoiceDraftFromSource`.
 */
final readonly class LegacyProjectPlanInvoiceSource
{
    public function __construct(private CreateInvoiceDraftFromSource $create) {}

    /**
     * @param  list<array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, kind: string, productId: int|null}>  $rawLines
     * @param  array<string, mixed>  $customer
     */
    public function convert(
        int $projectId,
        array $rawLines,
        array $customer,
        ?int $partnerId,
        string $currency,
        int $paymentTermsDays,
        string $idempotencyKey,
    ): InvoiceView {
        if ($rawLines === []) {
            throw new DomainException('legacy_project_plan_lines_invalid');
        }
        $lines = [];
        foreach ($rawLines as $rawLine) {
            $lines[] = $this->line($rawLine, $currency);
        }

        $issueDate = new DateTimeImmutable('today');
        $dueDate = $issueDate->modify(sprintf('+%d days', max(0, $paymentTermsDays)));

        $draft = new InvoiceDraftData(
            issueDate: $issueDate,
            dueDate: $dueDate,
            currency: $currency,
            customer: $customer,
            lines: $lines,
            discount: Discount::none($currency),
            partnerId: $partnerId,
        );

        $sourceKey = 'project-plan-time:'.$projectId.':'.substr(hash('sha256', $idempotencyKey), 0, 48);
        $snapshotSha256 = hash('sha256', json_encode([
            'project_id' => $projectId,
            'lines' => $rawLines,
        ], JSON_THROW_ON_ERROR));
        $source = new InvoiceDraftSource('project_time_batch', $sourceKey, 1, $snapshotSha256, $draft);

        return $this->create->handle($source, new IdempotencyKey('legacy-project-plan-invoice:'.$idempotencyKey));
    }

    /** @param array{desc: string, qty: float, unit: string, unitPrice: float, vatRate: float, kind: string, productId: int|null} $rawLine */
    private function line(array $rawLine, string $currency): InvoiceLineData
    {
        try {
            $quantity = $this->canonicalQuantity((string) $rawLine['qty']);
            $unitPriceMinor = Money::fromDecimal((string) $rawLine['unitPrice'], $currency)->minor();
            $taxRateBasisPoints = Money::fromDecimal((string) $rawLine['vatRate'], 'BPS')->minor();
        } catch (\Throwable $exception) {
            throw new DomainException('legacy_project_plan_lines_invalid', previous: $exception);
        }

        return new InvoiceLineData(
            $rawLine['desc'],
            $quantity,
            $unitPriceMinor,
            $taxRateBasisPoints,
            $rawLine['unit'],
            $rawLine['productId'],
            $rawLine['kind'],
        );
    }

    private function canonicalQuantity(string $quantity): string
    {
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,4}))?\z/D', trim($quantity), $parts) !== 1) {
            throw new DomainException('legacy_project_plan_lines_invalid');
        }

        return $parts[1].$parts[2].'.'.str_pad($parts[3] ?? '', 4, '0');
    }
}
