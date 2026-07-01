<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos can now be videos too: the media type distinguishes them and the
 * duration (seconds) is stored for video items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->string('media_type')->default('image')->after('status');
            $table->unsignedInteger('duration')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn(['media_type', 'duration']);
        });
    }
};
