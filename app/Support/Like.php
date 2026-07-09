<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prepare a user-supplied term for a case-insensitive LIKE query.
 */
final class Like
{
    /**
     * Lowercase the term and escape the LIKE metacharacters (\ % _) so they match
     * literally instead of acting as wildcards. Pair with a query that lowercases
     * the column and declares the escape char, e.g.
     *   ->whereRaw("LOWER(col) LIKE ? ESCAPE '\\'", ['%'.Like::escape($q).'%'])
     */
    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term));
    }
}
