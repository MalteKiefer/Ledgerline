<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

/**
 * One parsed row of a legacy `finance_projects.expenses` JSON array, ready to
 * become a `finance_project_ledger_entries` row. `amountMinor` is always a
 * positive integer; sign lives in `direction`, matching the new schema.
 */
final readonly class LegacyProjectExpenseRow
{
    /**
     * @param  array<string, mixed>  $legacyMetadata  Unknown/unmapped keys from the source row, retained verbatim.
     */
    public function __construct(
        public string $direction,
        public int $amountMinor,
        public string $currency,
        public ?string $occurredOn,
        public ?string $title,
        public ?string $note,
        public ?string $categoryReference,
        public ?string $paymentMethodReference,
        public array $legacyMetadata,
    ) {}
}
