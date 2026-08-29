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
        $cursorState = $this->state($filter, $types);
        $state = $cursorState['positions'];
        $last = $cursorState['last'];
        $items = [];
        /** @var array<string, ProjectDocumentMetadata> $heads */
        $heads = [];
        /** @var array<string, string|null> $nextCursors */
        $nextCursors = [];
        /** @var array<string, true> $emptySources */
        $emptySources = [];

        while (count($items) < $filter->perPage) {
            foreach ($types as $type) {
                if (! isset($heads[$type]) && ! $state[$type]['done'] && ! isset($emptySources[$type])) {
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
                    } elseif ($page->nextCursor === null || $page->nextCursor === $state[$type]['cursor']) {
                        $state[$type] = ['cursor' => null, 'done' => true];
                    } else {
                        $state[$type]['cursor'] = $page->nextCursor;
                        $emptySources[$type] = true;
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
            $item = $heads[$type];
            if ($last !== null && self::compareKeys(self::key($item), $last) <= 0) {
                throw new LogicException('document_source_cursor_order_invalid');
            }
            $items[] = $item;
            $last = self::key($item);
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

        return new ProjectDocumentSourcePage($items, $hasMore ? $this->cursor($filter, $types, $state, $last) : null);
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
        return self::compareKeys(self::key($a), self::key($b));
    }

    /** @return array{occurred_at: string, source_type: string, source_reference: string} */
    private static function key(ProjectDocumentMetadata $item): array
    {
        return [
            'occurred_at' => $item->occurredAt?->format('Y-m-d\TH:i:s.uP') ?? '0001-01-01T00:00:00.000000+00:00',
            'source_type' => $item->source->sourceType,
            'source_reference' => $item->source->sourceReference,
        ];
    }

    /**
     * @param  array{occurred_at: string, source_type: string, source_reference: string}  $a
     * @param  array{occurred_at: string, source_type: string, source_reference: string}  $b
     */
    private static function compareKeys(array $a, array $b): int
    {
        $aDate = new \DateTimeImmutable($a['occurred_at']);
        $bDate = new \DateTimeImmutable($b['occurred_at']);
        $seconds = $bDate->getTimestamp() <=> $aDate->getTimestamp();
        if ($seconds !== 0) {
            return $seconds;
        }
        $microseconds = $bDate->format('u') <=> $aDate->format('u');
        if ($microseconds !== 0) {
            return $microseconds;
        }
        $type = $a['source_type'] <=> $b['source_type'];

        return $type !== 0 ? $type : self::compareReferences($a['source_type'], $a['source_reference'], $b['source_reference']);
    }

    private static function compareReferences(string $type, string $a, string $b): int
    {
        if ($type === 'finance_series') {
            return $a <=> $b;
        }
        if ($type === 'bank_transaction_receipt') {
            [, $aTransaction, $aReceipt] = explode(':', $a, 3);
            [, $bTransaction, $bReceipt] = explode(':', $b, 3);
            $transaction = ((int) $aTransaction) <=> ((int) $bTransaction);

            return $transaction !== 0 ? $transaction : $aReceipt <=> $bReceipt;
        }

        return ((int) substr($a, strrpos($a, ':') + 1)) <=> ((int) substr($b, strrpos($b, ':') + 1));
    }

    /**
     * @param  list<string>  $types
     * @return array{positions: array<string, array{cursor: ?string, done: bool}>, last: array{occurred_at: string, source_type: string, source_reference: string}|null}
     */
    private function state(ProjectDocumentSourceFilter $filter, array $types): array
    {
        if ($filter->cursor === null) {
            return ['positions' => array_fill_keys($types, ['cursor' => null, 'done' => false]), 'last' => null];
        }
        $encoded = strtr($filter->cursor, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($encoded, true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($payload) || ($payload['v'] ?? null) !== 2 || ($payload['filter'] ?? null) !== $this->filterDigest($filter, $types) || ! is_array($payload['positions'] ?? null)) {
            throw new InvalidArgumentException('Invalid catalog cursor.');
        }
        $last = $payload['last'] ?? null;
        if ($last !== null) {
            if (! is_array($last) || ! is_string($last['occurred_at'] ?? null) || ! is_string($last['source_type'] ?? null) || ! is_string($last['source_reference'] ?? null)) {
                throw new InvalidArgumentException('Invalid catalog cursor.');
            }
            try {
                new \DateTimeImmutable($last['occurred_at']);
            } catch (\Throwable) {
                throw new InvalidArgumentException('Invalid catalog cursor.');
            }
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

        return ['positions' => $state, 'last' => $last === null ? null : [
            'occurred_at' => $last['occurred_at'],
            'source_type' => $last['source_type'],
            'source_reference' => $last['source_reference'],
        ]];
    }

    /**
     * @param  list<string>  $types
     * @param  array<string, array{cursor: ?string, done: bool}>  $state
     * @param  array{occurred_at: string, source_type: string, source_reference: string}|null  $last
     */
    private function cursor(ProjectDocumentSourceFilter $filter, array $types, array $state, ?array $last): string
    {
        $json = json_encode(['v' => 2, 'filter' => $this->filterDigest($filter, $types), 'positions' => $state, 'last' => $last], JSON_THROW_ON_ERROR);

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
            'from' => $filter->from?->format('Y-m-d\TH:i:s.uP'),
            'to' => $filter->to?->format('Y-m-d\TH:i:s.uP'),
        ], JSON_THROW_ON_ERROR));
    }
}
