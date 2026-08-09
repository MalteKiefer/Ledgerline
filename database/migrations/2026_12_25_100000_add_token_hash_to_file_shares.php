<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L1 hardening: store a sha256(token) alongside the plaintext token so public
 * links resolve by hash instead of by the raw capability. Aligns with the
 * InviteLink precedent (a DB dump no longer yields a directly-usable token).
 * The plaintext `token` column is kept so existing links + the owner-facing
 * share URL keep working; lookups switch to `token_hash`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_shares', function (Blueprint $table): void {
            $table->string('token_hash', 64)->nullable()->after('token');
        });

        // Backfill existing rows so their (plaintext) links keep resolving by hash.
        DB::table('file_shares')->select('id', 'token')->orderBy('id')->each(function ($row): void {
            if (is_string($row->token) && $row->token !== '') {
                DB::table('file_shares')->where('id', $row->id)
                    ->update(['token_hash' => hash('sha256', $row->token)]);
            }
        });

        Schema::table('file_shares', function (Blueprint $table): void {
            $table->unique('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('file_shares', function (Blueprint $table): void {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }
};
