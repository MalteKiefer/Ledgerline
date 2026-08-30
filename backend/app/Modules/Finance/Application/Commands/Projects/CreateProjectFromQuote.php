<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateProjectFromQuoteData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource;
use App\Modules\Finance\Application\DTOs\Projects\ProjectTarget;
use App\Modules\Finance\Application\Ports\Projects\ProjectFromQuoteTarget;

final readonly class CreateProjectFromQuote
{
    public function __construct(private ProjectFromQuoteTarget $target) {}

    public function handle(int $ownerId, ProjectQuoteSource $source, string $idempotencyKey): ProjectTarget
    {
        $data = new CreateProjectFromQuoteData($ownerId, $source, $idempotencyKey);

        return $this->target->create($data->ownerId, $data->source, $data->idempotencyKey);
    }
}
