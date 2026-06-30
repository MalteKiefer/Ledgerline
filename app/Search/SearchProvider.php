<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Contract for a global-search source.
 *
 * Each searchable entity type implements this once and is registered in
 * config/search.php. Adding a new entity to global search therefore means
 * writing one provider and adding one config line — nothing else changes.
 */
interface SearchProvider
{
    /**
     * The display group/heading these results appear under (e.g. "Customers").
     */
    public function group(): string;

    /**
     * Find up to $limit results matching the (already trimmed, non-empty) term.
     *
     * @return list<SearchResult>
     */
    public function search(string $term, int $limit): array;
}
