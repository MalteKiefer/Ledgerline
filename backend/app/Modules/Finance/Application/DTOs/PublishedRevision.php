<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs;

use DateTimeImmutable;

final readonly class PublishedRevision
{
    public function __construct(
        public DocumentRevisionId $revisionId,
        public int $revisionNumber,
        public string $path,
        public string $sha256,
        public DateTimeImmutable $publishedAt,
    ) {}
}
