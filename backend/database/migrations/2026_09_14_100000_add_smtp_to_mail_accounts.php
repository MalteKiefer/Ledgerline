<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add outbound (SMTP) config + an identity/signature to mail accounts.
        // All nullable: SMTP falls back to the IMAP login when left blank. The
        // SMTP password is kept encrypted at rest (encrypted cast), like the
        // IMAP password.
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable(); // ssl | starttls | none
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable(); // encrypted cast
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->text('signature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'smtp_host',
                'smtp_port',
                'smtp_encryption',
                'smtp_username',
                'smtp_password',
                'from_name',
                'reply_to',
                'signature',
            ]);
        });
    }
};
