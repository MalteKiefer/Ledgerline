<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            // OCR text extracted from the photo (signs / screenshots / receipts), so
            // text inside images is searchable — complements CLIP semantic search.
            $table->longText('ocr_text')->nullable()->after('name');
            $table->timestamp('ocr_at')->nullable()->after('ocr_text');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS gallery_photos_ocr_fts
                ON gallery_photos USING gin (to_tsvector('simple', coalesce(ocr_text, '')))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS gallery_photos_ocr_fts');
        }
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropColumn(['ocr_text', 'ocr_at']);
        });
    }
};
