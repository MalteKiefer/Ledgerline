<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the internal invoice-number counter in step with imported historical
 * invoices, so app-generated numbers continue the existing series instead of
 * colliding with or ignoring imported ones.
 *
 * The parsed prefix and zero-padding are adopted on the company profile, and
 * the per-prefix/per-year sequence is advanced to at least the imported number.
 */
class InvoiceNumberSequencer
{
    public function syncFromImported(string $number, ?int $fallbackYear = null): void
    {
        [$prefix, $year, $sequence, $pad] = $this->parse($number, $fallbackYear);

        if ($sequence === null) {
            return;
        }

        CompanyProfile::current()
            ->forceFill(['invoice_number_prefix' => $prefix, 'invoice_number_pad' => $pad])
            ->save();

        $row = DB::table('invoice_number_sequences')->where('prefix', $prefix)->where('year', $year)->first();

        if ($row === null) {
            DB::table('invoice_number_sequences')->insert([
                'prefix' => $prefix,
                'year' => $year,
                'last_number' => $sequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($sequence > $row->last_number) {
            DB::table('invoice_number_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $sequence, 'updated_at' => now()]);
        }
    }

    /**
     * @return array{0: string, 1: int, 2: ?int, 3: int}
     */
    private function parse(string $number, ?int $fallbackYear): array
    {
        $number = trim($number);
        $year = $fallbackYear ?? now()->year;

        // "<prefix>-<year>-<seq>" or "<year>-<seq>".
        if (preg_match('/^(?:(.*?)-)?(\d{4})-(\d+)$/', $number, $m) === 1) {
            return [$m[1] ?? '', (int) $m[2], (int) $m[3], strlen($m[3])];
        }

        // Fallback: any trailing number.
        if (preg_match('/^(.*?)(\d+)$/', $number, $m) === 1) {
            return [rtrim($m[1], '-/ '), $year, (int) $m[2], strlen($m[2])];
        }

        return ['', $year, null, 4];
    }
}
