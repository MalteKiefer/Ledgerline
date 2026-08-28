<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

use DateTimeImmutable;

final readonly class QuoteView
{
    /** @param array<array-key, mixed>|null $draft */
    public function __construct(
        public QuoteId $id,
        public string $status,
        public string $effectiveStatus,
        public ?int $partnerId,
        public ?string $number,
        public int $version,
        public ?QuoteRevisionRef $currentRevision,
        public ?array $draft,
        public int $netMinor,
        public int $vatMinor,
        public int $grossMinor,
        public string $currency,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $acceptedAt,
        public ?DateTimeImmutable $declinedAt,
        public ?DateTimeImmutable $convertedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
