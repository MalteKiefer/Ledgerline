<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs;

use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentLine;

final readonly class CreateRevisionData
{
    /**
     * @param  array<array-key, mixed>  $snapshot
     * @param  list<DocumentLine>  $lines
     */
    public function __construct(
        public string $seriesUuid,
        public array $snapshot,
        public array $lines,
        public Discount $discount,
        public ?string $changeReason = null,
    ) {}
}
