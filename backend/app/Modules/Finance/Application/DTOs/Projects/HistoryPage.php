<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class HistoryPage
{
    /** @param list<HistoryItemView> $items */
    public function __construct(
        public array $items,
        public int $perPage,
        public ?int $page = null,
        public ?int $total = null,
        public ?string $nextCursor = null,
    ) {}
}
