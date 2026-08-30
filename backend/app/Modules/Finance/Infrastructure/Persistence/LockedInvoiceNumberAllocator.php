<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\UserSetting;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use App\Support\DocumentNumber;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class LockedInvoiceNumberAllocator implements InvoiceNumberAllocator
{
    public function allocate(int $ownerId, string $issueDate): array
    {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Invoice number owner ID must be positive.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $date->format('Y-m-d') !== $issueDate) {
            throw new InvalidArgumentException('Invoice number dates must use valid YYYY-MM-DD values.');
        }

        $settings = UserSetting::query()->find($ownerId);
        $configuredFormat = $settings?->getAttribute('invoice_number_format');
        $format = is_string($configuredFormat) && trim($configuredFormat) !== ''
            ? $configuredFormat
            : DocumentNumber::DEFAULT_FORMAT;
        $configuredFloor = $settings?->getAttribute('invoice_next_number');
        $floor = is_numeric($configuredFloor) && (int) $configuredFloor > 0
            ? (int) $configuredFloor
            : 1;
        $year = (int) $date->format('Y');
        $carbonDate = Carbon::instance($date);

        return DB::transaction(function () use (
            $ownerId,
            $year,
            $floor,
            $format,
            $carbonDate,
        ): array {
            DB::table('finance_invoice_sequences')->insertOrIgnore([
                'user_id' => $ownerId,
                'series_key' => 'invoice',
                'year' => $year,
                'next_sequence' => $floor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('finance_invoice_sequences')
                ->where('user_id', $ownerId)
                ->where('series_key', 'invoice')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            if ($row === null || ! is_numeric($row->next_sequence)) {
                throw new LogicException('Invoice number sequence could not be initialized.');
            }

            $maximumUsed = DB::table('finance_invoices')
                ->where('user_id', $ownerId)
                ->where('year', $year)
                ->whereNotNull('number')
                ->max('sequence');
            $sequence = max(
                $floor,
                (int) $row->next_sequence,
                is_numeric($maximumUsed) ? (int) $maximumUsed + 1 : 1,
            );

            do {
                $number = DocumentNumber::format($format, $sequence, $carbonDate);
                $alreadyUsed = DB::table('finance_invoices')
                    ->where('user_id', $ownerId)
                    ->where('year', $year)
                    ->where('number', $number)
                    ->exists();
                if ($alreadyUsed) {
                    $sequence++;
                }
            } while ($alreadyUsed);

            DB::table('finance_invoice_sequences')
                ->where('user_id', $ownerId)
                ->where('series_key', 'invoice')
                ->where('year', $year)
                ->update([
                    'next_sequence' => $sequence + 1,
                    'updated_at' => now(),
                ]);

            return [
                'number' => $number,
                'year' => $year,
                'sequence' => $sequence,
            ];
        }, 1);
    }
}
