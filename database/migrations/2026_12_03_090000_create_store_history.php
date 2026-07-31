<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Last-N sealed-root history per (user, store). Retains the previous few sealed
 * root ciphertexts of every module/sharded store so a silently-dropped record
 * (detected by store:anomaly-scan) can be RECOVERED: the client fetches an
 * earlier version, decrypts it, and re-merges the lost record. The stored value
 * is the same opaque ciphertext as the live root — zero-knowledge is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('module'); // audit key: 'store:notes', 'gallery', 'invoices', …
            $table->unsignedBigInteger('version');
            $table->longText('ciphertext'); // sealed root snapshot (opaque)
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'module', 'version']);
            $table->unique(['user_id', 'module', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_history');
    }
};
