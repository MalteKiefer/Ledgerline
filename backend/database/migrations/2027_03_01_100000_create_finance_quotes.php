<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quotes (Angebote).
 *
 * `lines` deliberately uses the SAME shape as `invoices.lines`, extended with
 * `productId` and `kind`. That is what makes turning a quote into an invoice a
 * copy instead of a translation, and it lets the totals mathematics — the part
 * that must agree to the cent between client and server — stay one
 * implementation rather than two that drift.
 *
 * A quote number is sequential and unique per owner and year, but it is NOT
 * GoBD-gapless the way an invoice number is: nothing legal hangs on a quote.
 * The sequence still counts binned rows, because a customer holding a PDF that
 * says AN-2026-0007 should never receive a second, different AN-2026-0007.
 *
 * Additive: no existing finance table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('number', 64)->nullable();
            $table->unsignedInteger('seq')->nullable();
            $table->unsignedSmallInteger('year')->nullable();

            // draft → sent → accepted | declined | expired. Only 'sent' onwards
            // has a number, because that is when it leaves the house.
            $table->string('status', 16)->default('draft');

            $table->foreignId('partner_id')->nullable()
                ->constrained('finance_partners')->nullOnDelete();
            // Snapshot of who it was addressed to, kept alongside the partner id
            // so a later edit of the partner cannot rewrite a quote already sent.
            $table->json('customer')->nullable();

            $table->string('title', 300)->nullable();
            $table->date('issue_date')->nullable();
            // How long the price stands. Past it the quote counts as expired,
            // which is derived rather than stored — a date needs no cron job.
            $table->date('valid_until')->nullable();
            $table->string('currency', 8)->default('EUR');

            $table->json('lines')->nullable();
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();

            // Denormalised for lists and statistics, exactly as on invoices.
            $table->decimal('net', 12, 2)->nullable();
            $table->decimal('vat', 12, 2)->nullable();
            $table->decimal('gross', 12, 2)->nullable();

            $table->text('intro_text')->nullable();
            $table->text('outro_text')->nullable();
            $table->text('note')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();

            // What the quote became. Loose enough to survive the target being
            // deleted, because "this was accepted and billed" stays true.
            $table->foreignId('converted_invoice_id')->nullable()
                ->constrained('invoices')->nullOnDelete();
            $table->foreignId('converted_project_id')->nullable()
                ->constrained('finance_projects')->nullOnDelete();

            $table->string('pdf_path', 255)->nullable();

            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'issue_date']);
        });

        // Unique among an owner's live quotes of a year. Postgres and SQLite
        // both do partial indexes; MySQL does not, and this app runs on those two.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX finance_quotes_number_unique ON finance_quotes (user_id, year, number) '
                .'WHERE number IS NOT NULL AND deleted_at IS NULL'
            );
        }

        // Quote numbering lives next to the invoice numbering it mirrors, so the
        // company profile stays the one place a document number is configured.
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('quote_number_format', 40)->nullable()->after('invoice_number_format');
            $table->unsignedInteger('quote_next_number')->nullable()->after('quote_number_format');
            // Days a quote stands by default. Only a suggestion for the form.
            $table->unsignedSmallInteger('quote_valid_days')->nullable()->after('quote_next_number');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['quote_number_format', 'quote_next_number', 'quote_valid_days']);
        });
        Schema::dropIfExists('finance_quotes');
    }
};
