<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notes are now plaintext, so shares hold a frozen server-side snapshot
        // (title + markdown) instead of a client-encrypted blob, with an
        // optional hashed password. Drop the old client-crypto rows.
        DB::table('note_shares')->delete();

        Schema::table('note_shares', function (Blueprint $table): void {
            $table->string('title')->default('')->after('id');
            $table->longText('content')->nullable()->after('title');
            $table->string('password_hash')->nullable()->after('content');
        });

        // The client-encryption columns are no longer used; make them nullable
        // so inserts don't require them (kept for a clean, reversible history).
        Schema::table('note_shares', function (Blueprint $table): void {
            $table->text('cipher')->nullable()->change();
            $table->string('nonce')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('note_shares', function (Blueprint $table): void {
            $table->dropColumn(['title', 'content', 'password_hash']);
        });
    }
};
