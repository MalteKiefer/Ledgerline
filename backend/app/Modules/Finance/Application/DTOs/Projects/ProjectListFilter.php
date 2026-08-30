<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectListFilter
{
    public function __construct(
        public int $ownerId,
        public ?string $q = null,
        public ?ProjectStatus $status = null,
        public ?ProjectKind $kind = null,
        public ?string $partnerReference = null,
        public ?string $parentUuid = null,
        public bool $archived = false,
        public ?DateTimeImmutable $startsFrom = null,
        public ?DateTimeImmutable $startsTo = null,
        public ?DateTimeImmutable $dueFrom = null,
        public ?DateTimeImmutable $dueTo = null,
        public string $sort = 'updated_at',
        public string $direction = 'desc',
        public int $page = 1,
        public int $perPage = 25,
    ) {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Project owner ID must be positive.');
        }
        if (! in_array($sort, ['updated_at', 'name', 'starts_on', 'due_on', 'status'], true)) {
            throw new InvalidArgumentException('Project sort is invalid.');
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Project sort direction is invalid.');
        }
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Project pagination is invalid.');
        }
        if ($parentUuid !== null
            && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/Di', $parentUuid) !== 1) {
            throw new InvalidArgumentException('Parent project UUID is invalid.');
        }
    }
}
