<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive — SMTP send/reply/forward (the deferred §2.7 item 8). Adds the
 * per-account SMTP transport columns so the user can compose + reply + forward
 * from an archived mailbox and have the sent copy appended back to the origin
 * Sent folder (over IMAP). The SMTP password is an operational secret — the
 * ONLY new encrypted-at-rest value (Laravel `encrypted` cast, APP_KEY) — never a
 * message body/header. Also adds a per-user plaintext mail signature appended to
 * composed bodies (non-secret presentation, like the other display prefs).
 *
 * All additive + nullable: an account with no SMTP configured simply cannot send
 * (the compose/reply/forward endpoints return no_smtp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->string('smtp_host', 255)->nullable()->after('encryption');
            $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_username', 255)->nullable()->after('smtp_port');
            $table->text('smtp_password')->nullable()->after('smtp_username'); // encrypted cast (APP_KEY)
            $table->string('smtp_encryption', 16)->nullable()->after('smtp_password'); // ssl|tls|starttls|none
            $table->string('from_name', 255)->nullable()->after('smtp_encryption');
            $table->string('from_email', 255)->nullable()->after('from_name');
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->text('mail_signature')->nullable()->after('mail_allow_scripts');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'from_name', 'from_email',
            ]);
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('mail_signature');
        });
    }
};
