<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device UnifiedPush endpoint (an HTTPS ntfy topic URL the device registered).
 * Stored encrypted at the app layer (App\Models\PersonalAccessToken cast). One
 * endpoint per device token; a user may have several devices → several endpoints.
 * SendPushJob POSTs notification payloads here (SSRF-guarded via OutboundUrl).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            // Ciphertext of an https URL — a text column (encryption inflates length).
            $table->text('push_endpoint')->nullable()->after('os_version');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('push_endpoint');
        });
    }
};
