<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side file content search. The pivot dropped the client CLIP/OCR index;
 * this rebuilds it relationally: extracted plaintext text lives in `search_text`
 * (PDF text layer via poppler, images via tesseract, text/markdown/csv read
 * directly) and `indexed_at` records when it was last derived. On PostgreSQL a
 * GIN full-text index over to_tsvector('simple', search_text) makes @@ queries
 * fast; SQLite falls back to LIKE (no index needed at our scale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->longText('search_text')->nullable();
            $table->timestamp('indexed_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX files_search_text_gin ON files USING gin (to_tsvector('simple', coalesce(search_text, '')))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS files_search_text_gin');
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['search_text', 'indexed_at']);
        });
    }
};
