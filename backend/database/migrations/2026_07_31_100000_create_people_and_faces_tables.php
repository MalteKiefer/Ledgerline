<?php

declare(strict_types=1);

use App\Support\Vector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * People (clustered faces) and the individual detected faces. A face has a
 * bounding box, a detection score and a face embedding (pgvector, Postgres only)
 * used to cluster faces into people. `people.contact_id` is a forward hook for
 * the upcoming vCard contacts module; naming stays free-text until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->uuid('contact_id')->nullable()->index(); // future vCard contact link
            $table->uuid('cover_face_id')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedInteger('faces_count')->default(0);
            $table->timestamps();
        });

        Schema::create('faces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->uuid('person_id')->nullable()->index();
            $table->float('det_score')->default(0);
            // Bounding box, normalised 0..1 of the image dimensions.
            $table->float('box_x1')->default(0);
            $table->float('box_y1')->default(0);
            $table->float('box_x2')->default(0);
            $table->float('box_y2')->default(0);
            $table->string('thumb_path')->nullable();
            // Manual assignment (merge / reassign) that a re-cluster must not undo.
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });

        if (Vector::available()) {
            DB::statement('ALTER TABLE faces ADD COLUMN embedding vector(512)');
            DB::statement('CREATE INDEX faces_embedding_hnsw ON faces USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faces');
        Schema::dropIfExists('people');
    }
};
