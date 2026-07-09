<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether the pgvector extension is usable on the current connection. Similarity
 * features (CLIP embeddings, face embeddings) require it; a plain Postgres (or
 * sqlite) dev/test database simply skips the vector columns and vector queries.
 */
class Vector
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return self::$available = false;
        }

        try {
            return self::$available = DB::selectOne("SELECT 1 AS ok FROM pg_available_extensions WHERE name = 'vector'") !== null;
        } catch (Throwable) {
            return self::$available = false;
        }
    }

    /**
     * Write a pgvector embedding to a row, plus any extra scalar columns in the
     * same UPDATE. $table and the $extra keys are code-controlled column names
     * (never user input), so they are safe to interpolate; the values bind.
     *
     * @param  array<int, int|float>  $vector
     * @param  array<string, mixed>  $extra  column => value set alongside the embedding
     */
    public static function store(string $table, string|int $id, array $vector, array $extra = []): void
    {
        $sets = ['embedding = ?::vector'];
        $bindings = ['['.implode(',', $vector).']'];
        foreach ($extra as $column => $value) {
            $sets[] = $column.' = ?';
            $bindings[] = $value;
        }
        $bindings[] = $id;

        DB::update('UPDATE '.$table.' SET '.implode(', ', $sets).' WHERE id = ?', $bindings);
    }
}
