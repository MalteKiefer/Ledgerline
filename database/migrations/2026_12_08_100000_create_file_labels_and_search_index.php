<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Files stage 2: coloured labels (a user-defined, many-to-many taxonomy distinct
 * from the free-text tags column) + the pgsql GIN full-text index over the
 * files.search_text column the content indexer fills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('color', 16)->default('#6b7280');
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });

        Schema::create('file_label_file', function (Blueprint $table): void {
            $table->foreignId('file_label_id')->constrained('file_labels')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->primary(['file_label_id', 'file_id']);
            $table->index('file_id');
        });

        // Full-text index (pgsql only; sqlite falls back to LIKE in the search
        // controller). Guarded so a non-pgsql connection is a no-op.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS files_search_text_gin ON files USING gin (to_tsvector('simple', coalesce(search_text, '')))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS files_search_text_gin');
        }
        Schema::dropIfExists('file_label_file');
        Schema::dropIfExists('file_labels');
    }
};
