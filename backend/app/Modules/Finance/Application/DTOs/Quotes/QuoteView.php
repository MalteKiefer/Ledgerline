<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

use DateTimeImmutable;

final readonly class QuoteView
{
    /**
     * @param  array<array-key, mixed>|null  $draft
     * @param  list<array{source_revision_id: int, target_type: string, target_reference: string, target_id: int|null, created_at: string}>  $conversions
     * @param  array{uuid: string, revision_id: int, state: string, attempts: int, last_error_code: string|null, queued_at: string, sent_at: string|null, failed_at: string|null}|null  $latestDelivery
     */
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
        public array $conversions = [],
        public ?array $latestDelivery = null,
    ) {}
}
