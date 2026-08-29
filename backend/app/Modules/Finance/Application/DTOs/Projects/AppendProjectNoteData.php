<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AppendProjectNoteData
{
    public const TYPES = ['note', 'decision', 'call', 'email', 'meeting', 'correction'];

    public const VISIBILITIES = ['internal', 'customer'];

    public string $body;

    public function __construct(
        public ProjectId $projectId,
        public string $type,
        public string $visibility,
        string $body,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?int $supersedesNoteId = null,
    ) {
        $this->body = trim($body);
        if ($actorId < 1
            || ! in_array($type, self::TYPES, true)
            || ! in_array($visibility, self::VISIBILITIES, true)
            || mb_strlen($this->body) < 1
            || mb_strlen($this->body) > 100_000
            || (($type === 'correction') !== ($supersedesNoteId !== null))
            || ($supersedesNoteId !== null && $supersedesNoteId < 1)) {
            throw new InvalidArgumentException('project_note_invalid');
        }
    }
}
