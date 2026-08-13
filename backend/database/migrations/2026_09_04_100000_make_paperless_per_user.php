<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paperless becomes a per-user integration: each user connects their own
 * instance URL + token. The credentials move from the global app_settings row
 * onto user_settings, cached terms gain a user_id, and the previous global
 * config (plus its cached terms) is handed to the first user so nothing breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('paperless_enabled')->default(false);
            $table->text('paperless_url')->nullable();   // encrypted at rest
            $table->text('paperless_token')->nullable(); // encrypted at rest
            $table->timestamp('paperless_synced_at')->nullable();
            // Per-user default channels pre-selected for new reminders.
            $table->json('reminder_channels')->nullable();
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        $app = DB::table('app_settings')->first();

        // Carry the previous global Paperless config to the first user.
        if ($firstUserId !== null && $app !== null && ($app->paperless_url ?? null)) {
            DB::table('user_settings')->updateOrInsert(
                ['user_id' => $firstUserId],
                [
                    'paperless_enabled' => $app->paperless_enabled ?? false,
                    'paperless_url' => $app->paperless_url,
                    'paperless_token' => $app->paperless_token ?? null,
                    'paperless_synced_at' => $app->paperless_synced_at ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        Schema::table('paperless_terms', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['kind', 'paperless_id']);
        });

        // Existing cached terms belonged to the (single) global config → first user.
        if ($firstUserId !== null) {
            DB::table('paperless_terms')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        } else {
            DB::table('paperless_terms')->delete();
        }

        Schema::table('paperless_terms', function (Blueprint $table): void {
            $table->unique(['user_id', 'kind', 'paperless_id']);
        });

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['paperless_enabled', 'paperless_url', 'paperless_token', 'paperless_synced_at']);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('paperless_enabled')->default(false);
            $table->text('paperless_url')->nullable();
            $table->text('paperless_token')->nullable();
            $table->timestamp('paperless_synced_at')->nullable();
        });

        Schema::table('paperless_terms', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'kind', 'paperless_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['kind', 'paperless_id']);
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['paperless_enabled', 'paperless_url', 'paperless_token', 'paperless_synced_at', 'reminder_channels']);
        });
    }
};
