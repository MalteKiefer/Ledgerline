<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('invoice_mail_enabled')->default(false);
            $table->text('invoice_smtp_host')->nullable();       // encrypted
            $table->integer('invoice_smtp_port')->nullable();
            $table->string('invoice_smtp_encryption', 8)->nullable(); // tls|ssl|none
            $table->text('invoice_smtp_username')->nullable();   // encrypted
            $table->text('invoice_smtp_password')->nullable();   // encrypted
            $table->string('invoice_from_email')->nullable();
            $table->string('invoice_from_name')->nullable();
            $table->string('invoice_mail_subject')->nullable();  // template, :number
            $table->text('invoice_mail_body')->nullable();       // template, :number
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'invoice_mail_enabled', 'invoice_smtp_host', 'invoice_smtp_port',
                'invoice_smtp_encryption', 'invoice_smtp_username', 'invoice_smtp_password',
                'invoice_from_email', 'invoice_from_name', 'invoice_mail_subject', 'invoice_mail_body',
            ]);
        });
    }
};
