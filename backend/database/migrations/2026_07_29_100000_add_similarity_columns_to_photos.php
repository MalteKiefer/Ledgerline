<?php

declare(strict_types=1);

use App\Support\Vector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Similarity fields for content-based duplicate detection: a 64-bit perceptual
 * hash (near-identical pre-pass, works everywhere) and a CLIP embedding stored
 * as a pgvector column with an HNSW cosine index (added only on Postgres; the
 * sqlite test database keeps just the pHash column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->bigInteger('phash')->nullable()->index();
            $table->timestamp('embedded_at')->nullable();
        });

        if (Vector::available()) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE photos ADD COLUMN embedding vector(512)');
            DB::statement('CREATE INDEX photos_embedding_hnsw ON photos USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS photos_embedding_hnsw');
            DB::statement('ALTER TABLE photos DROP COLUMN IF EXISTS embedding');
        }

        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn(['phash', 'embedded_at']);
        });
    }
};
