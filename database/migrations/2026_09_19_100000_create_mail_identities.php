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
        // Multiple sender identities per account (Thunderbird-style): each is a
        // From address with its own display name, reply-to and signature. The
        // legacy from_name/reply_to/signature columns on mail_accounts remain
        // for backward-compat reads; new sends resolve through an identity.
        Schema::create('mail_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Data migration: seed one default identity per existing account from
        // its current sending fields (from_email = the IMAP username, matching
        // the old smtpConfig()). Read without global scopes so every user's
        // accounts are covered.
        $now = now();
        DB::table('mail_accounts')->orderBy('id')->each(function ($account) use ($now): void {
            DB::table('mail_identities')->insert([
                'mail_account_id' => $account->id,
                'from_name' => $account->from_name,
                'from_email' => $account->username,
                'reply_to' => $account->reply_to,
                'signature' => $account->signature,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_identities');
    }
};
