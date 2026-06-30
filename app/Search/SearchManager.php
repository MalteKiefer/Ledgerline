<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Runs the configured search providers and aggregates their results.
 *
 * The provider list is injected (resolved from config/search.php), so the
 * manager has no knowledge of any concrete entity type.
 */
class SearchManager
{
    /**
     * @param  list<SearchProvider>  $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly int $limitPerGroup = 8,
    ) {}

    /**
     * Search every provider and return non-empty groups in provider order.
     *
     * @return array<string, list<SearchResult>>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $groups = [];

        foreach ($this->providers as $provider) {
            $results = $provider->search($term, $this->limitPerGroup);

            if ($results !== []) {
                $groups[$provider->group()] = $results;
            }
        }

        return $groups;
    }
}
