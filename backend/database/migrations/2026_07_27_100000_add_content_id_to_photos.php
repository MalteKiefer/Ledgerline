<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apple Live Photo pairing key. iOS stores a shared ContentIdentifier on both the
 * still (HEIC) and its motion clip (MOV); we record it so the two uploads can be
 * paired into one motion photo regardless of arrival order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->string('content_id')->nullable()->after('checksum');
            $table->index('content_id');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropIndex(['content_id']);
            $table->dropColumn('content_id');
        });
    }
};
