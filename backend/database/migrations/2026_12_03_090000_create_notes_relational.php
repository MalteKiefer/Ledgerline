<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot, Phase 1 (Notes). One row per note — the opaque
 * sealed notes store (notes_store/notes_blobs) is being retired. Per-row writes
 * in DB transactions + FK cascade + soft-delete replace the client rebase-merge
 * and orphan sweeps, eliminating the whole-blob last-writer-wins loss class.
 *
 * Non-secret content (title/body/tags) is stored plaintext + indexed so the
 * server can search/sort/paginate. A GIN full-text index is added on Postgres
 * only (the sqlite test DB skips it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500)->nullable();
            $table->text('body')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('pinned')->default(false);
            // Optimistic concurrency for the rare concurrent-tab edit (no merge engine).
            $table->unsignedBigInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'pinned']);
            $table->index(['user_id', 'updated_at']);
        });

        // Postgres full-text search over title + body (skipped on the sqlite test DB).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX notes_fts_idx ON notes USING GIN (to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(body,'')))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
