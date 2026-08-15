<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone receipts ("Fremdbelege") gain the fields the recogniser/matcher need to
 * link them to a bank transaction — including the many-receipts-for-one-charge case
 * (e.g. Amazon splitting an order into several shipment invoices that settle as one
 * card charge): `amount`/`date` mirror what OCR recognised (editable by the owner),
 * `order_ref` is a payment/order reference some merchants print (Amazon's
 * "Zahlungsreferenznummer") that groups several receipts belonging to one charge,
 * `doc_number` is the recognised invoice/receipt number (display + de-dup signal for
 * accidental re-uploads of the same document). All additive + nullable — no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->nullable()->after('vat');
            $table->date('date')->nullable()->after('amount');
            $table->string('order_ref', 64)->nullable()->after('date');
            $table->string('doc_number', 64)->nullable()->after('order_ref');
        });
    }

    public function down(): void
    {
        Schema::table('finance_receipts', function (Blueprint $table): void {
            $table->dropColumn(['amount', 'date', 'order_ref', 'doc_number']);
        });
    }
};
