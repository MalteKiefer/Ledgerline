<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_internal_shares', function (Blueprint $table) {
            // viewer = read-only (default, existing shares); editor = may contribute
            // photos into a shared ALBUM (collaborative album).
            $table->string('role', 16)->default('viewer')->after('gallery_album_id');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_internal_shares', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
