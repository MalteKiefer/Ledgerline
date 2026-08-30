<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\Ports\Quotes\QuoteNumberAllocator;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Support\DocumentNumber;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DatabaseQuoteNumberAllocator implements QuoteNumberAllocator
{
    public function __construct(private QuoteSettings $settings) {}

    public function allocate(int $ownerId, string $issueDate): array
    {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Quote number owner ID must be positive.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $date->format('Y-m-d') !== $issueDate) {
            throw new InvalidArgumentException('Quote number dates must use valid YYYY-MM-DD values.');
        }

        $year = (int) $date->format('Y');
        $floor = $this->settings->quoteNumberFloor($ownerId);
        $format = $this->settings->quoteNumberFormat($ownerId);
        $carbonDate = Carbon::instance($date);

        return DB::transaction(function () use (
            $ownerId,
            $year,
            $floor,
            $format,
            $carbonDate,
        ): array {
            DB::table('finance_quote_number_sequences')->insertOrIgnore([
                'user_id' => $ownerId,
                'year' => $year,
                'next_sequence' => $floor,
            ]);
            $sequenceRow = DB::table('finance_quote_number_sequences')
                ->where('user_id', $ownerId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            if ($sequenceRow === null || ! is_numeric($sequenceRow->next_sequence)) {
                throw new InvalidArgumentException('Quote number sequence could not be initialized.');
            }

            $maximumUsed = DB::table('finance_quote_series')
                ->where('user_id', $ownerId)
                ->where('sequence_year', $year)
                ->max('sequence_number');
            $sequence = max(
                $floor,
                (int) $sequenceRow->next_sequence,
                is_numeric($maximumUsed) ? (int) $maximumUsed + 1 : 1,
            );

            do {
                $number = DocumentNumber::format($format, $sequence, $carbonDate);
                $alreadyUsed = DB::table('finance_quote_series')
                    ->where('user_id', $ownerId)
                    ->where('number', $number)
                    ->exists();
                if ($alreadyUsed) {
                    $sequence++;
                }
            } while ($alreadyUsed);

            DB::table('finance_quote_number_sequences')
                ->where('user_id', $ownerId)
                ->where('year', $year)
                ->update(['next_sequence' => $sequence + 1]);

            return [
                'number' => $number,
                'year' => $year,
                'sequence' => $sequence,
            ];
        }, 1);
    }
}
