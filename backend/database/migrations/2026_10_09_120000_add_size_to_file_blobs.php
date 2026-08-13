<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the stored (ciphertext) byte size of each uploaded blob. Lets sync read
 * the authoritative size from the DB instead of issuing one object-storage HEAD
 * per new file — a large batch (hundreds/thousands of files) would otherwise make
 * a single sync take tens of seconds and be aborted (data loss).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_blobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('size')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('file_blobs', function (Blueprint $table): void {
            $table->dropColumn('size');
        });
    }
};
