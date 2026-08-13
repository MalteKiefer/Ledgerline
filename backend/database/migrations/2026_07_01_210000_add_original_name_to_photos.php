<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the original uploaded filename permanently. The display name may be
 * rewritten by the filename template, but the original is retained for
 * duplicate detection and reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('name');
        });

        // Backfill existing rows with the current display name.
        Schema::hasColumn('photos', 'original_name')
            && DB::table('photos')->whereNull('original_name')->update(['original_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn('original_name');
        });
    }
};
