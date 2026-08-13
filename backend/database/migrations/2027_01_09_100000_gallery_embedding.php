<?php

declare(strict_types=1);

use App\Support\Vector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery: CLIP embeddings for semantic search ("Baum" → tree photos) and, later,
 * near-duplicate detection. The 512-dim vector column + HNSW cosine index are
 * pgvector-only (guarded by Vector::available()); a plain Postgres/sqlite dev/
 * test DB just keeps embedded_at and skips the vector column + vector queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->timestamp('embedded_at')->nullable()->after('duration');
        });

        if (Vector::available()) {
            DB::statement('ALTER TABLE gallery_photos ADD COLUMN IF NOT EXISTS embedding vector(512)');
            DB::statement('CREATE INDEX IF NOT EXISTS gallery_photos_embedding_idx ON gallery_photos USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        if (Vector::available()) {
            DB::statement('DROP INDEX IF EXISTS gallery_photos_embedding_idx');
            DB::statement('ALTER TABLE gallery_photos DROP COLUMN IF EXISTS embedding');
        }
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropColumn('embedded_at');
        });
    }
};
