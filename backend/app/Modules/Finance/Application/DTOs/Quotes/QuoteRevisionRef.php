<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

use DateTimeImmutable;

final readonly class QuoteRevisionRef
{
    /** @param array<array-key, mixed> $snapshot */
    public function __construct(
        public int $id,
        public int $revisionNumber,
        public ?int $previousRevisionId,
        public string $status,
        public array $snapshot,
        public int $netMinor,
        public int $vatMinor,
        public int $grossMinor,
        public string $currency,
        public ?string $pdfPath,
        public ?string $pdfSha256,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
