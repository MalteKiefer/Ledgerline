<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectNoteFilter
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $visibilities
     */
    public function __construct(
        public ?string $q = null,
        public array $types = [],
        public array $visibilities = [],
        public ?int $authorId = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
        if (($q !== null && mb_strlen(trim($q)) > 255)
            || array_diff($types, AppendProjectNoteData::TYPES) !== []
            || array_diff($visibilities, AppendProjectNoteData::VISIBILITIES) !== []
            || ($authorId !== null && $authorId < 1)
            || ($from !== null && $to !== null && $from > $to)
            || $page < 1
            || $perPage < 1
            || $perPage > 100) {
            throw new InvalidArgumentException('project_note_filter_invalid');
        }
    }
}
