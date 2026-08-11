<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable ML / gallery settings on the workspace singleton. NULL = inherit
 * the env/config default; a set value overrides config('ml.*') at boot. Lets the
 * site-settings Gallery page toggle ML, pick models, and tune thresholds without
 * a redeploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('ml_enabled')->nullable();
            $table->boolean('ml_face_enabled')->nullable();
            $table->string('ml_url')->nullable();
            $table->string('ml_clip_model')->nullable();
            $table->string('ml_face_model')->nullable();
            $table->float('ml_search_distance')->nullable();
            $table->float('ml_dup_distance')->nullable();
            $table->float('ml_face_min_score')->nullable();
            $table->float('ml_face_match_distance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'ml_enabled', 'ml_face_enabled', 'ml_url', 'ml_clip_model', 'ml_face_model',
                'ml_search_distance', 'ml_dup_distance', 'ml_face_min_score', 'ml_face_match_distance',
            ]);
        });
    }
};
