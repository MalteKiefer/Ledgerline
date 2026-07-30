<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Explore). One row per GPS track. The raw ordered
 * point list is location PII (Art. 8 sensitive) → `points` carries an `encrypted`
 * cast (a JSON array of {lat,lng,ele?,t?}); the free-text note is encrypted too.
 * Aggregate stats (distance/duration/ascent/descent/surfaces) stay plaintext so
 * the server can list/sort. The optional raw track file (gpx/kml/…) lives
 * plaintext on the file disk at blob_path; parsing stays client-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explore_tracks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 300);
            $table->string('source_format', 24); // recorded|imported|planned
            $table->longText('points');          // encrypted cast (JSON [{lat,lng,ele?,t?}])
            $table->json('stats')->nullable();   // plaintext aggregates
            $table->text('note')->nullable();    // encrypted cast
            $table->string('blob_path', 255)->nullable(); // plaintext raw file on disk, or null
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'source_format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explore_tracks');
    }
};
