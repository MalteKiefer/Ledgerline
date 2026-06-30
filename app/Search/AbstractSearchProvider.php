<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Shared helpers for search providers.
 *
 * Provides a database-agnostic, case-insensitive LIKE pattern. Column names
 * passed to whereRaw are always hardcoded by the providers (never user input),
 * and the search term is bound as a parameter, so this stays injection-safe.
 */
abstract class AbstractSearchProvider implements SearchProvider
{
    /**
     * Build a lowercase "%term%" pattern for use with LOWER(col) LIKE ?.
     */
    protected function wildcard(string $term): string
    {
        return '%'.mb_strtolower($term).'%';
    }
}
