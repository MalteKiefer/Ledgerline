<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->boolean('paperless_enabled')->default(false)->after('webhook_secret');
            // URL + API token: usable in the clear at runtime, encrypted at rest.
            $table->text('paperless_url')->nullable()->after('paperless_enabled');
            $table->text('paperless_token')->nullable()->after('paperless_url');
            $table->timestamp('paperless_synced_at')->nullable()->after('paperless_token');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['paperless_enabled', 'paperless_url', 'paperless_token', 'paperless_synced_at']);
        });
    }
};
