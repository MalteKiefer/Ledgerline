<?php

declare(strict_types=1);

use App\Support\Vector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery face recognition (opt-in). Detected faces are grouped into people by
 * face-embedding cosine similarity; the owner names/merges/reassigns them. The
 * face embedding column + HNSW index are pgvector-only (Vector::available()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('cover_face_id')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });

        Schema::create('gallery_faces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_person_id')->nullable()->constrained()->nullOnDelete();
            $table->json('box');            // [x1,y1,x2,y2] normalised 0..1
            $table->float('score')->default(0);
            $table->string('crop_path')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'gallery_person_id']);
            $table->index('gallery_photo_id');
        });

        if (Vector::available()) {
            DB::statement('ALTER TABLE gallery_faces ADD COLUMN IF NOT EXISTS embedding vector(512)');
            DB::statement('CREATE INDEX IF NOT EXISTS gallery_faces_embedding_idx ON gallery_faces USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        if (Vector::available()) {
            DB::statement('DROP INDEX IF EXISTS gallery_faces_embedding_idx');
        }
        Schema::dropIfExists('gallery_faces');
        Schema::dropIfExists('gallery_people');
    }
};
