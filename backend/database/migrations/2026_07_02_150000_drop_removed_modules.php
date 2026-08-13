<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop everything belonging to the removed CRM and finance modules.
 *
 * The application is now Files + Gallery only: invoices, expenses, income,
 * time tracking, units, customers, projects, contacts and branches are gone,
 * so their tables (and the polymorphic attachable on files plus the finance
 * identity fields on company_profiles) go with them. Destructive by design.
 */
return new class extends Migration
{
    /** Child tables first so foreign keys never block a drop. */
    private array $tables = [
        'invoice_lines',
        'invoices',
        'invoice_number_sequences',
        'expenses',
        'income_entries',
        'time_entries',
        'units',
        'contact_emails',
        'contact_phones',
        'contacts',
        'branches',
        'project_tag',
        'projects',
        'customers',
    ];

    public function up(): void
    {
        $pg = Schema::getConnection()->getDriverName() === 'pgsql';

        foreach ($this->tables as $table) {
            // PostgreSQL needs CASCADE for lingering cross-table dependencies.
            if ($pg) {
                Schema::getConnection()->statement("drop table if exists \"{$table}\" cascade");
            } else {
                Schema::dropIfExists($table);
            }
        }

        // Files no longer attach to a customer/project/invoice. Drop the morphs
        // index first — SQLite cannot drop a column that an index still covers.
        try {
            Schema::table('files', fn (Blueprint $table) => $table->dropIndex('files_attachable_type_attachable_id_index'));
        } catch (Throwable) {
            // Index already gone on this connection.
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['attachable_type', 'attachable_id']);
        });

        // The company profile keeps only the gallery and vault settings.
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name', 'address_line1', 'address_line2', 'postal_code',
                'city', 'country', 'vat_id', 'tax_number', 'register_court',
                'register_number', 'managing_director', 'email', 'phone',
                'website', 'iban', 'bic', 'bank_name', 'logo_path',
                'small_business', 'default_language', 'default_currency',
                'default_tax_rate', 'invoice_number_prefix', 'invoice_number_next',
                'invoice_number_pad', 'payment_terms_days', 'invoice_footer_text',
                'tax_display', 'paper_size',
            ]);
        });
    }

    public function down(): void
    {
        // Irreversible: the modules and their data are gone for good.
    }
};
