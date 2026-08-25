<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which columns the mail list shows, and in which order.
 *
 * A display preference like the units and the clock format, so it lives with
 * them (user_settings -> displayPrefs -> GET /me.preferences) rather than in a
 * table of its own: the phone app gets the same choice for free, and there is
 * one place where "how things are shown" is decided.
 *
 * Nullable = not chosen, and the client uses its default set. Storing the
 * default explicitly would freeze it: a column added later would never appear
 * for anyone who had once opened the picker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->json('mail_columns')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('mail_columns');
        });
    }
};
