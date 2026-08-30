<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectDocumentFilter
{
    /**
     * @param  list<string>  $sourceTypes
     * @param  list<string>  $roles
     * @param  list<string>  $mimeGroups
     * @param  list<string>  $availabilities
     */
    public function __construct(
        public ProjectId $projectId,
        public ?string $q = null,
        public array $sourceTypes = [],
        public array $roles = [],
        public array $mimeGroups = [],
        public array $availabilities = [],
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public string $state = 'active',
        public int $page = 1,
        public int $perPage = 50,
    ) {
        if ($page < 1 || $perPage < 1 || $perPage > 100 || ($from !== null && $to !== null && $from > $to)) {
            throw new InvalidArgumentException('Project document filter is invalid.');
        }
        if (array_diff($sourceTypes, ProjectDocumentSourceFilter::TYPES) !== []
            || array_diff($roles, ['source_quote', 'quote', 'invoice', 'payment', 'receipt', 'file', 'photo', 'other']) !== []
            || array_diff($mimeGroups, ['pdf', 'image', 'other']) !== []
            || array_diff($availabilities, ['available', 'deleted', 'missing']) !== []
            || ! in_array($state, ['active', 'detached', 'all'], true)
            || ($q !== null && mb_strlen(trim($q)) > 255)) {
            throw new InvalidArgumentException('Project document filter values are invalid.');
        }
    }
}
