<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use InvalidArgumentException;

final readonly class CreateProjectFromQuoteData
{
    public function __construct(public int $ownerId, public ProjectQuoteSource $source, public string $idempotencyKey)
    {
        if ($ownerId < 1 || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('project_quote_request_invalid');
        }
    }
}
