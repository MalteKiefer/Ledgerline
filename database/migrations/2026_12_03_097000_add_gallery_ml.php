<?php

declare(strict_types=1);

use App\Support\Vector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-integrate CLIP semantic search + face recognition into the plaintext-
 * relational Gallery (deferred during the ZK pivot). Adds:
 *
 *  - gallery_photos.embedded_at  (plaintext marker that ML has run — a NULL
 *    means "uploaded while ML was off", the backfill command's work list)
 *  - gallery_photos.embedding    (pgvector 512-d CLIP image vector) + an hnsw
 *    cosine index — ONLY on Postgres with the vector extension. On sqlite (the
 *    test driver) the vector column/index are skipped entirely; semantic search
 *    degrades to empty results there.
 *  - gallery_people / gallery_faces tables. Faces carry their own pgvector
 *    embedding (+ hnsw) on Postgres so a new face can be grouped to the nearest
 *    known person by cosine distance.
 *
 * Every pgvector-specific DDL statement is guarded by Vector::available() so a
 * plain-Postgres or sqlite database migrates cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $vector = Vector::available();

        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->timestamp('embedded_at')->nullable()->after('exif');
        });

        if ($vector) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE gallery_photos ADD COLUMN embedding vector(512)');
            DB::statement('CREATE INDEX gallery_photos_embedding_hnsw ON gallery_photos USING hnsw (embedding vector_cosine_ops)');
        }

        Schema::create('gallery_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200)->nullable();
            // Set once faces exist; no FK (would be circular with gallery_faces).
            $table->unsignedBigInteger('cover_face_id')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('gallery_faces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_photo_id')->constrained('gallery_photos')->cascadeOnDelete();
            $table->foreignId('gallery_person_id')->nullable()->constrained('gallery_people')->nullOnDelete();
            $table->decimal('score', 5, 4)->default(0);
            $table->json('box'); // normalised [x1,y1,x2,y2]
            $table->string('crop_path', 255)->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'gallery_person_id']);
            $table->index('gallery_photo_id');
        });

        if ($vector) {
            DB::statement('ALTER TABLE gallery_faces ADD COLUMN embedding vector(512)');
            DB::statement('CREATE INDEX gallery_faces_embedding_hnsw ON gallery_faces USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        // Dropping the tables removes their vector columns/indexes with them.
        Schema::dropIfExists('gallery_faces');
        Schema::dropIfExists('gallery_people');

        if (Vector::available() && Schema::hasColumn('gallery_photos', 'embedding')) {
            DB::statement('DROP INDEX IF EXISTS gallery_photos_embedding_hnsw');
            DB::statement('ALTER TABLE gallery_photos DROP COLUMN embedding');
        }

        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropColumn('embedded_at');
        });
    }
};
