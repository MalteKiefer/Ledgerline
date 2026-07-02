<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the source URL of the user's Pocket-ID avatar so it can be re-fetched
 * on demand ("refresh avatar") without a fresh sign-in. The downloaded image
 * itself lives on the object-storage disk and its path stays in `avatar`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_url')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_url');
        });
    }
};
