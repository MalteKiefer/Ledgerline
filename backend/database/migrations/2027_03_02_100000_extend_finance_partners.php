<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer management: the fields a partner needs to be more than an address,
 * plus a contact log.
 *
 * `kind` is NOT added here — the column has existed since the pivot and no code
 * path has ever written or read it, so every row holds NULL. It is given its
 * meaning now (customer|supplier|both|lead) rather than adding a second column
 * that would mean almost the same thing.
 *
 * Additive throughout: nothing existing is changed or dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_partners', function (Blueprint $table): void {
            // Customer number. Optional, because a partner is useful without one
            // and a supplier rarely has ours.
            $table->string('customer_number', 32)->nullable()->after('name');
            // Payment terms in days and a standing discount, both used to prefill
            // a new document rather than to compute anything by themselves.
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('currency');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('payment_terms_days');
            // Where goods go, when that is not where the invoice goes.
            $table->text('delivery_address')->nullable()->after('address');
            // Archived rather than deleted: an old customer's documents must keep
            // their partner, so hiding it from the pickers is the useful state.
            $table->timestamp('archived_at')->nullable();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX finance_partners_customer_number_unique ON finance_partners (user_id, customer_number) '
                .'WHERE customer_number IS NOT NULL AND deleted_at IS NULL'
            );
        }

        Schema::create('finance_partner_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_partner_id')->constrained('finance_partners')->cascadeOnDelete();

            // What happened: a call, a meeting, a mail, or a plain note.
            $table->string('kind', 16)->default('note');
            $table->text('body');
            // When it happened, which is not when it was typed.
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'finance_partner_id', 'occurred_at']);
        });

        // Customer numbering next to the invoice and quote numbering it mirrors,
        // so every document/party number is configured in one place.
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('customer_number_format', 40)->nullable()->after('quote_valid_days');
            $table->unsignedInteger('customer_next_number')->nullable()->after('customer_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['customer_number_format', 'customer_next_number']);
        });
        Schema::dropIfExists('finance_partner_notes');
        Schema::table('finance_partners', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_number', 'payment_terms_days', 'discount_percent', 'delivery_address', 'archived_at',
            ]);
        });
    }
};
