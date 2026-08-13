<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// When the owner last saw a finished export on the Downloads page. Ready
// exports with a NULL seen_at drive the nav badge shown after a reload.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn('seen_at');
        });
    }
};
