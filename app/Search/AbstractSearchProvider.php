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
     * Build a lowercase "%term%" pattern for LOWER(col) LIKE ? ESCAPE '\'.
     *
     * The LIKE metacharacters \, % and _ in the user's term are escaped so they
     * match literally instead of acting as wildcards (e.g. "50%" no longer
     * matches everything).
     */
    protected function wildcard(string $term): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term));

        return '%'.$escaped.'%';
    }

    /**
     * Add a grouped, case-insensitive LIKE match across the given columns (OR),
     * plus an optional exact tag match, to the query. Column names are always
     * hardcoded by the caller (never user input); the term is bound.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  list<string>  $columns
     * @return \Illuminate\Database\Eloquent\Builder<*> the same query, for chaining
     */
    protected function matchAny($query, array $columns, string $like, ?string $tagTerm = null, string $tagColumn = 'tags')
    {
        return $query->where(function ($q) use ($columns, $like, $tagTerm, $tagColumn): void {
            foreach (array_values($columns) as $i => $col) {
                $q->{$i === 0 ? 'whereRaw' : 'orWhereRaw'}("LOWER({$col}) LIKE ? ESCAPE '\\'", [$like]);
            }
            if ($tagTerm !== null) {
                $q->orWhereJsonContains($tagColumn, $tagTerm);
            }
        });
    }
}
