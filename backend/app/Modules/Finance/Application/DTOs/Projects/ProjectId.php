<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use InvalidArgumentException;

final readonly class ProjectId
{
    public function __construct(public int $ownerId, public string $uuid)
    {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Project owner ID must be positive.');
        }

        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/Di', $uuid) !== 1) {
            throw new InvalidArgumentException('Project UUID is invalid.');
        }
    }
}
