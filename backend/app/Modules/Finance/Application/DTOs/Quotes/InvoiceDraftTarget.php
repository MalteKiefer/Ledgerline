<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

use InvalidArgumentException;

final readonly class InvoiceDraftTarget
{
    public function __construct(
        public string $targetReference,
        public ?int $targetId,
    ) {
        if (trim($targetReference) === '' || strlen($targetReference) > 255) {
            throw new InvalidArgumentException('Invoice target reference must contain between 1 and 255 bytes.');
        }
        if ($targetId !== null && $targetId < 1) {
            throw new InvalidArgumentException('Invoice target ID must be positive or null.');
        }
    }
}
