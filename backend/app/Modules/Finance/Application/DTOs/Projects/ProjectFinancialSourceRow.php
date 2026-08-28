<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;

final readonly class ProjectFinancialSourceRow
{
    /** @param list<string> $settlingTransactionReferences */
    public function __construct(public string $sourceReference, public int $signedMinor, public string $currency, public DateTimeImmutable $occurredOn, public array $settlingTransactionReferences = []) {}
}
