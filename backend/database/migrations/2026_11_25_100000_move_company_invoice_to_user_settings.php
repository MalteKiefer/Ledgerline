<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-user: company identity + invoice numbering become PER-USER (each user
 * invoices under their own business + their own number sequence). The columns
 * mirror the former app_settings company/invoice fields; existing workspace values
 * are copied onto the admin (first) user's settings row. The app_settings columns
 * are left in place (unused) to avoid a destructive change on live data.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $stringCols = [
        'company_name', 'company_email', 'company_phone', 'company_tax_id', 'company_vat_id',
        'company_iban', 'company_bic', 'company_bank_name', 'company_logo_path',
        'invoice_number_prefix', 'invoice_number_format', 'invoice_accent_color',
        'invoice_heading_color', 'invoice_template',
    ];

    /** @var array<int, string> */
    private array $textCols = [
        'company_address', 'invoice_footer_text', 'invoice_payment_methods', 'invoice_payment_terms_text',
    ];

    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            foreach ($this->stringCols as $c) {
                $table->string($c)->nullable();
            }
            foreach ($this->textCols as $c) {
                $table->text($c)->nullable();
            }
            $table->unsignedInteger('invoice_number_padding')->nullable();
            $table->unsignedInteger('invoice_next_number')->nullable();
            $table->unsignedInteger('invoice_payment_terms_days')->nullable();
            $table->decimal('invoice_default_vat_rate', 5, 2)->nullable();
        });

        // Copy the workspace company/invoice values onto the admin (first) user.
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');
        $app = DB::table('app_settings')->first();
        if ($adminId !== null && $app !== null) {
            $cols = array_merge($this->stringCols, $this->textCols, [
                'invoice_number_padding', 'invoice_next_number', 'invoice_payment_terms_days', 'invoice_default_vat_rate',
            ]);
            $copy = [];
            foreach ($cols as $c) {
                if (property_exists($app, $c) && $app->{$c} !== null) {
                    $copy[$c] = $app->{$c};
                }
            }
            if ($copy !== []) {
                DB::table('user_settings')->updateOrInsert(['user_id' => $adminId], $copy);
            }
        }
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(array_merge($this->stringCols, $this->textCols, [
                'invoice_number_padding', 'invoice_next_number', 'invoice_payment_terms_days', 'invoice_default_vat_rate',
            ]));
        });
    }
};
