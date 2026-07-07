<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Extracted, searchable plain text (OCR for scanned PDFs/images) for full-text
// file search. A GIN trigram index on Postgres keeps LIKE '%term%' fast.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->longText('content')->nullable();
            $table->timestamp('content_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS files_content_trgm ON files USING gin (LOWER(content) gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS files_content_trgm');
        }
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn(['content', 'content_at']);
        });
    }
};
