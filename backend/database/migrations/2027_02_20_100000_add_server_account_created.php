<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember whether the setup created the account on the target.
 *
 * Without it the removal instructions can only offer both cases and let the
 * reader guess. Guessing wrong in one direction leaves an account behind; in
 * the other it runs `userdel` on an account the operator was already using —
 * for `root`, on the account that owns the machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            // Null for rows created before this was recorded: unknown, and the
            // UI says so rather than inventing an answer.
            $table->boolean('account_created')->nullable()->after('restricted_key');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn('account_created');
        });
    }
};
