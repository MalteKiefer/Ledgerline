<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared\Workflow;

final readonly class Transition
{
    public function __construct(
        public string $from,
        public string $to,
    ) {}
}
