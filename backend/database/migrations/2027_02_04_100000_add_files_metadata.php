<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            // Extracted per-filetype metadata (image EXIF, PDF info, audio/video
            // ffprobe, STL geometry, …). Populated by a worker job on upload/replace;
            // null = not yet extracted / not extractable. Sanitised scalars only.
            $table->json('metadata')->nullable()->after('search_text');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
};
