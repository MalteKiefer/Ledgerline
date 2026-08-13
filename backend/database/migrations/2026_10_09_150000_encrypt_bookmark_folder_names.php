<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seal bookmark folder names too (zero-knowledge): the name column now holds the
 * sealed {c,n} JSON string, flagged by is_encrypted. Widened so the ciphertext
 * fits. color/icon stay plaintext (decoration, not user content).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookmark_folders', function (Blueprint $table): void {
            $table->boolean('is_encrypted')->default(false)->after('name');
            $table->string('name', 2048)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookmark_folders', function (Blueprint $table): void {
            $table->dropColumn('is_encrypted');
        });
    }
};
