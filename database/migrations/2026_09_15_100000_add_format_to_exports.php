<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Archive format an export is built as: 'zip' (default) | 'tar' | 'targz' | 'tarbz2'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table): void {
            $table->string('format')->default('zip')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table): void {
            $table->dropColumn('format');
        });
    }
};
