<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use DateTimeImmutable;

final readonly class InvoiceView
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public InvoiceId $id,
        public string $uuid,
        public string $kind,
        public ?string $number,
        public string $status,
        public DateTimeImmutable $issueDate,
        public DateTimeImmutable $dueDate,
        public ?int $partnerId,
        public ?int $projectId,
        public int $netMinor,
        public int $vatMinor,
        public int $grossMinor,
        public int $allocatedMinor,
        public int $openMinor,
        public string $currency,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $snapshot,
        public ?string $sourceType,
        public ?string $sourceKey,
        public ?int $sourceRevisionId,
        public ?string $sourceSnapshotSha256,
    ) {}
}
