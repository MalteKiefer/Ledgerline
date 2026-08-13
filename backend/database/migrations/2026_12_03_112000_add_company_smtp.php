<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user COMPANY SMTP — a dedicated mail transport for sending invoices under
 * the user's own business identity, deliberately SEPARATE from the workspace
 * notification SMTP (AppSettings). All additive/nullable columns on
 * user_settings; the password carries an `encrypted` cast on the model (operational
 * secret, like paperless_token). Non-destructive: no finance table touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('company_smtp_enabled')->default(false)->after('invoice_payment_terms_text');
            $table->string('company_smtp_host')->nullable()->after('company_smtp_enabled');
            $table->unsignedInteger('company_smtp_port')->nullable()->after('company_smtp_host');
            $table->string('company_smtp_encryption', 8)->nullable()->after('company_smtp_port'); // tls|ssl|null
            $table->string('company_smtp_username')->nullable()->after('company_smtp_encryption');
            $table->text('company_smtp_password')->nullable()->after('company_smtp_username'); // encrypted cast
            $table->string('company_smtp_from_address')->nullable()->after('company_smtp_password');
            $table->string('company_smtp_from_name')->nullable()->after('company_smtp_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'company_smtp_enabled', 'company_smtp_host', 'company_smtp_port',
                'company_smtp_encryption', 'company_smtp_username', 'company_smtp_password',
                'company_smtp_from_address', 'company_smtp_from_name',
            ]);
        });
    }
};
