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
        $types = $filter->sourceTypes === [] ? ProjectDocumentSourceFilter::TYPES : $filter->sourceTypes;
        $types = array_values(array_unique($types));
        sort($types);
        $state = $this->state($filter, $types);
        $items = [];
        /** @var array<string, ProjectDocumentMetadata> $heads */
        $heads = [];
        /** @var array<string, string|null> $nextCursors */
        $nextCursors = [];
        $emptyPageHops = 0;

        while (count($items) < $filter->perPage) {
            foreach ($types as $type) {
                while (! isset($heads[$type]) && ! $state[$type]['done']) {
                    $source = $this->one($type);
                    $page = $source->search($ownerId, new ProjectDocumentSourceFilter(
                        $ownerId,
                        $filter->q,
                        [$type],
                        $filter->mimeGroups,
                        $filter->from,
                        $filter->to,
                        $state[$type]['cursor'],
                        1,
                    ));
                    if ($page->items !== []) {
                        $heads[$type] = $page->items[0];
                        $nextCursors[$type] = $page->nextCursor;
                        break;
                    }
                    if ($page->nextCursor === null || $page->nextCursor === $state[$type]['cursor']) {
                        $state[$type] = ['cursor' => null, 'done' => true];
                        break;
                    }
                    $state[$type]['cursor'] = $page->nextCursor;
                    $emptyPageHops++;
                    if ($emptyPageHops > 10_000) {
                        throw new LogicException('document_source_cursor_did_not_converge');
                    }
                }
            }

            if ($heads === []) {
                break;
            }
            uasort($heads, self::compare(...));
            $type = array_key_first($heads);
            if (! is_string($type)) {
                throw new LogicException('Project document merge head is invalid.');
            }
            $items[] = $heads[$type];
            $next = $nextCursors[$type] ?? null;
            $state[$type] = ['cursor' => $next, 'done' => $next === null];
            unset($heads[$type], $nextCursors[$type]);
        }

        $hasMore = false;
        foreach ($state as $position) {
            if (! $position['done']) {
                $hasMore = true;
                break;
            }
        }

        return new ProjectDocumentSourcePage($items, $hasMore ? $this->cursor($filter, $types, $state) : null);
    }

    /** @return list<ProjectDocumentSource> */
    private function matching(string $type): array
    {
        return array_values(array_filter($this->sources, static fn (ProjectDocumentSource $source): bool => $source->supports($type)));
    }

    private function one(string $type): ProjectDocumentSource
    {
        $sources = $this->matching($type);
        if (count($sources) !== 1) {
            throw new LogicException(count($sources) === 0 ? 'document_source_adapter_missing' : 'document_source_adapter_ambiguous');
        }

        return $sources[0];
    }

    private static function compare(ProjectDocumentMetadata $a, ProjectDocumentMetadata $b): int
    {
        $date = ($b->occurredAt?->getTimestamp() ?? 0) <=> ($a->occurredAt?->getTimestamp() ?? 0);

        return $date !== 0 ? $date : [$a->source->sourceType, $a->source->sourceReference] <=> [$b->source->sourceType, $b->source->sourceReference];
    }

    /**
     * @param  list<string>  $types
     * @return array<string, array{cursor: ?string, done: bool}>
     */
    private function state(ProjectDocumentSourceFilter $filter, array $types): array
    {
        if ($filter->cursor === null) {
            return array_fill_keys($types, ['cursor' => null, 'done' => false]);
        }
        $encoded = strtr($filter->cursor, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($encoded, true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($payload) || ($payload['v'] ?? null) !== 1 || ($payload['filter'] ?? null) !== $this->filterDigest($filter, $types) || ! is_array($payload['positions'] ?? null)) {
            throw new InvalidArgumentException('Invalid catalog cursor.');
        }

        $positions = $payload['positions'];
        $state = [];
        foreach ($types as $type) {
            $position = $positions[$type] ?? null;
            $done = is_array($position) ? ($position['done'] ?? null) : null;
            $cursor = is_array($position) ? ($position['cursor'] ?? null) : null;
            if (! is_array($position) || ! is_bool($done) || (! is_string($cursor) && $cursor !== null)) {
                throw new InvalidArgumentException('Invalid catalog cursor.');
            }
            $state[$type] = ['cursor' => $cursor, 'done' => $done];
        }

        return $state;
    }

    /**
     * @param  list<string>  $types
     * @param  array<string, array{cursor: ?string, done: bool}>  $state
     */
    private function cursor(ProjectDocumentSourceFilter $filter, array $types, array $state): string
    {
        $json = json_encode(['v' => 1, 'filter' => $this->filterDigest($filter, $types), 'positions' => $state], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @param list<string> $types */
    private function filterDigest(ProjectDocumentSourceFilter $filter, array $types): string
    {
        return hash('sha256', json_encode([
            'owner_id' => $filter->ownerId,
            'q' => $filter->q !== null ? trim($filter->q) : null,
            'source_types' => $types,
            'mime_groups' => $filter->mimeGroups,
            'from' => $filter->from?->format(DATE_ATOM),
            'to' => $filter->to?->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
    }
}
