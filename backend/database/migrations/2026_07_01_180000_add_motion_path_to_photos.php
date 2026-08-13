<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motion photos embed a short MP4 clip after the JPEG. When one is found the
 * extracted clip is stored separately and referenced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->string('motion_path')->nullable()->after('medium_path');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn('motion_path');
        });
    }
};
