<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AppendDocumentNoteData
{
    public string $seriesUuid;

    public string $body;

    public function __construct(
        public int $ownerId,
        string $seriesUuid,
        public ?int $revisionId,
        public string $type,
        public string $visibility,
        string $body,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?int $supersedesNoteId = null,
    ) {
        $this->seriesUuid = strtolower($seriesUuid);
        $this->body = trim($body);
        if ($ownerId < 1
            || $actorId < 1
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $this->seriesUuid) !== 1
            || ($revisionId !== null && $revisionId < 1)
            || ! in_array($type, AppendProjectNoteData::TYPES, true)
            || ! in_array($visibility, AppendProjectNoteData::VISIBILITIES, true)
            || mb_strlen($this->body) < 1
            || mb_strlen($this->body) > 100_000
            || (($type === 'correction') !== ($supersedesNoteId !== null))
            || ($supersedesNoteId !== null && $supersedesNoteId < 1)) {
            throw new InvalidArgumentException('document_note_invalid');
        }
    }
}
