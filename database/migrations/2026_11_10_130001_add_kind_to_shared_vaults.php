<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Distinguishes a shared password-Tresor from a shared folder. Existing rows are
// all password vaults. The column drives index filtering so each client module
// only sees its own kind; the crypto/membership/store machinery is identical.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_vaults', function (Blueprint $table): void {
            $table->string('kind')->default('password')->after('owner_id');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('shared_vaults', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
