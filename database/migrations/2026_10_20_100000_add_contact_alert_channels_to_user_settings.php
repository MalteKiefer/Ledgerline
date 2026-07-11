<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user notification-channel choice for contact birthday / anniversary
 * alerts. Empty = off. The contacts themselves stay zero-knowledge; the client
 * detects a due date (it holds the data) and relays a one-off message through
 * the chosen channels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->json('contact_birthday_channels')->nullable()->after('theme');
            $table->json('contact_anniversary_channels')->nullable()->after('contact_birthday_channels');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['contact_birthday_channels', 'contact_anniversary_channels']);
        });
    }
};
