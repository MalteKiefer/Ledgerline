<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Explore). One row per user holding the photo↔track
 * matching tolerances. Non-secret preferences, so plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explore_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('coupling_time_tolerance_s')->default(3600);
            $table->unsignedInteger('coupling_distance_tolerance_m')->default(100);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explore_settings');
    }
};
