<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoicing-lifecycle slice (additive): email dispatch + due/overdue reminders.
 *
 * - sent_at: when the invoice PDF was emailed to the customer (server-set).
 * - reminded_at: when the owner was last reminded about it being overdue.
 * - reminder_count: how many overdue reminders have gone out.
 *
 * "Overdue" itself is DERIVED (status='sent' AND due_date < today) — no column.
 * All three columns are nullable/defaulted and server-managed (forceFill); no
 * existing invoice column is touched, so the live invoices stay untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('sent_at')->nullable()->after('paid_at');
            $table->timestamp('reminded_at')->nullable()->after('sent_at');
            $table->unsignedInteger('reminder_count')->default(0)->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['sent_at', 'reminded_at', 'reminder_count']);
        });
    }
};
