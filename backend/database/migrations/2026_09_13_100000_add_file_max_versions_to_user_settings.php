<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-user cap on how many previous versions of a file are kept (1–10).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('file_max_versions')->default(10)->after('gallery_columns');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('file_max_versions');
        });
    }
};
