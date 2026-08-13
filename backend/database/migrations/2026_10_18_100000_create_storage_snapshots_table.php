<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily point-in-time storage usage per module, so the System page can show a
 * trend (growth over time) rather than only the current total. One row per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('captured_on')->unique();
            $table->unsignedBigInteger('files_bytes')->default(0);
            $table->unsignedBigInteger('gallery_bytes')->default(0);
            $table->unsignedBigInteger('database_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_snapshots');
    }
};
