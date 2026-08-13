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
}
