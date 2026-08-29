<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectDocumentMetadata
{
    /** @param array<string, int|string> $capabilityParameters */
    public function __construct(
        public ProjectDocumentSourceRef $source,
        public string $title,
        public ?string $mime,
        public ?int $size,
        public ?string $sha256,
        public string $documentType,
        public ?string $documentLabel,
        public ?DateTimeImmutable $occurredAt,
        public string $availability = 'available',
        public ?string $capabilityRoute = null,
        public array $capabilityParameters = [],
    ) {
        if (trim($title) === '' || strlen($title) > 255 || ! in_array($availability, ['available', 'deleted'], true)) {
            throw new InvalidArgumentException('Project document metadata is invalid.');
        }
        if ($size !== null && $size < 0) {
            throw new InvalidArgumentException('Project document size is invalid.');
        }
        if ($sha256 !== null && preg_match('/\A[0-9a-f]{64}\z/Di', $sha256) !== 1) {
            throw new InvalidArgumentException('Project document digest is invalid.');
        }
        foreach ($capabilityParameters as $key => $value) {
            if (! is_string($key) || (! is_string($value) && ! is_int($value))) {
                throw new InvalidArgumentException('Project document capability parameters are invalid.');
            }
        }
    }

    /** @return array<string, int|string|null> */
    public function snapshot(): array
    {
        return [
            'source_type' => $this->source->sourceType,
            'source_reference' => $this->source->sourceReference,
            'title' => trim($this->title),
            'mime' => $this->mime,
            'size' => $this->size,
            'sha256' => $this->sha256 !== null ? strtolower($this->sha256) : null,
            'document_type' => $this->documentType,
            'document_label' => $this->documentLabel,
            'occurred_at' => $this->occurredAt?->format(DATE_ATOM),
        ];
    }
}
