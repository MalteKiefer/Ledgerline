<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use InvalidArgumentException;

final readonly class PaymentSuggestionResult
{
    /** @param list<PaymentSuggestionCandidate> $candidates */
    public function __construct(
        public string $status,
        public array $candidates,
        public bool $requiresConfirmation = true,
    ) {
        if (! in_array($status, ['none', 'suggested', 'ambiguous'], true)) {
            throw new InvalidArgumentException('Payment suggestion status is invalid.');
        }
    }
}
