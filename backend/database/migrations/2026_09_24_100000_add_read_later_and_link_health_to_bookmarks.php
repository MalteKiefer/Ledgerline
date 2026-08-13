<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Read-later queue (read_later flag + read_at once read) and dead-link
// checking (last_checked_at scan stamp; dead_at set while a link fails).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->boolean('read_later')->default(false)->after('favorite');
            $table->timestamp('read_at')->nullable()->after('read_later');
            $table->timestamp('last_checked_at')->nullable()->after('read_at');
            $table->timestamp('dead_at')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropColumn(['read_later', 'read_at', 'last_checked_at', 'dead_at']);
        });
    }
};
