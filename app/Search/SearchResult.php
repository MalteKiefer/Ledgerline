<?php

declare(strict_types=1);

namespace App\Search;

/**
 * A single global-search hit, ready for display and linking.
 *
 * Providers map their models into these immutable rows so the search view does
 * not need to know anything about the underlying entity types.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $group,
        public string $title,
        public ?string $subtitle,
        public string $url,
    ) {}
}
