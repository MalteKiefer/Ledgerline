<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Quotes;

interface QuoteSettings
{
    public function quoteNumberFormat(int $ownerId): string;

    public function quoteNumberFloor(int $ownerId): int;

    public function defaultValidityDays(int $ownerId): int;

    public function invoicePaymentTermsDays(int $ownerId): int;

    public function ownerTimezone(int $ownerId): string;

    /** @return array{name: string, address: string} */
    public function senderIdentity(int $ownerId): array;
}
