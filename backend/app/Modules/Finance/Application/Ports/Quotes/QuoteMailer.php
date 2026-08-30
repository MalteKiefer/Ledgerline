<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;

interface QuoteMailer
{
    public function assertConfigured(int $ownerId): void;

    public function assertRevisionReady(QuoteRevisionRef $revision): void;

    public function dispatch(int $ownerId, int $deliveryId): void;
}
