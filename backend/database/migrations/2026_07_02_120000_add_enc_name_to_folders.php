<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a zero-knowledge encrypted-name column to folders.
 *
 * When a vault is configured, folders created in the browser store their real
 * name only as ciphertext in enc_name (sealed with the vault key); the plain
 * name column stays empty. Existing plaintext folders keep their name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->text('enc_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->dropColumn('enc_name');
        });
    }
};
