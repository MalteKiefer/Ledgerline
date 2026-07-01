<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Assigns gapless, sequential invoice numbers per prefix and year.
 *
 * The sequence row is locked for update inside a transaction so concurrent
 * finalisations cannot produce duplicate or gapped numbers. The first number of
 * a prefix/year uses the company's configured start number.
 */
class InvoiceNumberGenerator
{
    public function assign(Invoice $invoice): void
    {
        $company = CompanyProfile::current();
        $prefix = $company->invoice_number_prefix ?: 'RE';
        $year = (int) $invoice->issue_date->format('Y');

        $next = DB::transaction(function () use ($prefix, $year, $company): int {
            $sequence = DB::table('invoice_number_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $number = max(1, (int) $company->invoice_number_next);
                DB::table('invoice_number_sequences')->insert([
                    'prefix' => $prefix,
                    'year' => $year,
                    'last_number' => $number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $number;
            }

            $number = $sequence->last_number + 1;
            DB::table('invoice_number_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $number, 'updated_at' => now()]);

            return $number;
        });

        $invoice->year = $year;
        $invoice->sequence = $next;
        $invoice->number = sprintf('%s-%d-%04d', $prefix, $year, $next);
    }
}
