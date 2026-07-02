<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the sharer decide whether the recipient may download the shared note as
 * Markdown or PDF. Off by default — a shared link is read-only unless the
 * creator opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('note_shares', function (Blueprint $table): void {
            $table->boolean('allow_download')->default(false)->after('has_password');
        });
    }

    public function down(): void
    {
        Schema::table('note_shares', function (Blueprint $table): void {
            $table->dropColumn('allow_download');
        });
    }
};
