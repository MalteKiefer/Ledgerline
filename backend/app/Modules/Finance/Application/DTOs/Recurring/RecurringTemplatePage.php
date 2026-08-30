<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

final readonly class RecurringTemplatePage
{
    /** @param list<RecurringTemplateView> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
