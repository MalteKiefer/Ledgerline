<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Catalog;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentCatalog;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use InvalidArgumentException;
use LogicException;

final class CompositeProjectDocumentCatalog implements ProjectDocumentCatalog
{
    /** @var list<ProjectDocumentSource> */
    private array $sources;

    /** @param iterable<ProjectDocumentSource> $sources */
    public function __construct(iterable $sources)
    {
        $this->sources = [];
        foreach ($sources as $source) {
            if (! $source instanceof ProjectDocumentSource) {
                throw new InvalidArgumentException('Invalid project document source adapter.');
            }
            $this->sources[] = $source;
        }
    }

    public function supports(string $sourceType): bool
    {
        return count($this->matching($sourceType)) === 1;
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        $sources = $this->matching($ref->sourceType);
        if (count($sources) !== 1) {
            throw new LogicException(count($sources) === 0 ? 'document_source_adapter_missing' : 'document_source_adapter_ambiguous');
        }

        return $sources[0]->resolve($ownerId, $ref);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId) {
            throw new InvalidArgumentException('Document catalog owner mismatch.');
        }
        $offset = $this->offset($filter->cursor);
        $types = $filter->sourceTypes === [] ? ProjectDocumentSourceFilter::TYPES : $filter->sourceTypes;
        $items = [];
        foreach ($types as $type) {
            $sources = $this->matching($type);
            if (count($sources) !== 1) {
                throw new LogicException(count($sources) === 0 ? 'document_source_adapter_missing' : 'document_source_adapter_ambiguous');
            }
            $page = $sources[0]->search($ownerId, new ProjectDocumentSourceFilter($ownerId, $filter->q, [$type], $filter->mimeGroups, $filter->from, $filter->to, null, min(100, $offset + $filter->perPage + 1)));
            array_push($items, ...$page->items);
        }
        usort($items, static function (ProjectDocumentMetadata $a, ProjectDocumentMetadata $b): int {
            $date = ($b->occurredAt?->getTimestamp() ?? 0) <=> ($a->occurredAt?->getTimestamp() ?? 0);

            return $date !== 0 ? $date : [$a->source->sourceType, $a->source->sourceReference] <=> [$b->source->sourceType, $b->source->sourceReference];
        });
        $pageItems = array_slice($items, $offset, $filter->perPage);

        return new ProjectDocumentSourcePage($pageItems, count($items) > $offset + $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
    }

    /** @return list<ProjectDocumentSource> */
    private function matching(string $type): array
    {
        return array_values(array_filter($this->sources, static fn (ProjectDocumentSource $source): bool => $source->supports($type)));
    }

    private function offset(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        }
        $decoded = base64_decode($cursor, true);
        if (! is_string($decoded) || preg_match('/\A[0-9]+\z/D', $decoded) !== 1 || (int) $decoded > 99) {
            throw new InvalidArgumentException('Invalid catalog cursor.');
        }

        return (int) $decoded;
    }
}
