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
            // Outgoing mail (SMTP). Credentials are encrypted at rest via the
            // model's casts; the app needs them in the clear at runtime to send.
            $table->boolean('mail_enabled')->default(false);
            $table->text('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable(); // tls | ssl | null
            $table->text('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->text('smtp_from_address')->nullable();
            $table->text('smtp_from_name')->nullable();

            // NTFY push notifications.
            $table->boolean('ntfy_enabled')->default(false);
            $table->text('ntfy_url')->nullable();   // e.g. https://ntfy.sh
            $table->text('ntfy_topic')->nullable();
            $table->text('ntfy_token')->nullable();  // optional bearer/access token

            // Generic webhook (JSON POST, optional HMAC secret).
            $table->boolean('webhook_enabled')->default(false);
            $table->text('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'mail_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
                'smtp_username', 'smtp_password', 'smtp_from_address', 'smtp_from_name',
                'ntfy_enabled', 'ntfy_url', 'ntfy_topic', 'ntfy_token',
                'webhook_enabled', 'webhook_url', 'webhook_secret',
            ]);
        });
    }
};
