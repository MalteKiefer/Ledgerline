<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceMigration;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyPaymentLinkMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Resumable command-only migration of the legacy invoice/payment slice to
 * finance-v2. Never inserts into a `finance_*` table itself — every write
 * goes through LegacyInvoiceMigration/LegacyPaymentLinkMigration, which
 * call the same idempotent Application commands (ImportLegacyInvoice,
 * RecordPayment, AllocatePayment) an interactive user would. Safe to
 * interrupt and re-run: each owner's `finance_invoice_migration_checkpoints`
 * row resumes exactly where it stopped, and every underlying write is
 * idempotent regardless.
 */
final class MigrateFinanceInvoiceSlice extends Command
{
    protected $signature = 'finance:migrate-invoice-slice
        {--user=* : Migrate only these owner IDs}
        {--all-owners : Migrate every owner that has legacy invoices}';

    protected $description = 'Resumably migrate legacy invoices, bank-transaction payment links, and paid markers to finance-v2';

    public function handle(LegacyInvoiceMigration $invoices, LegacyPaymentLinkMigration $payments): int
    {
        $userIds = $this->resolveOwnerIds();
        if ($userIds === []) {
            $this->info('No owners to migrate.');

            return self::SUCCESS;
        }

        $failedOwners = 0;
        foreach ($userIds as $ownerId) {
            $ok = $this->migrateOwner($ownerId, $invoices, $payments);
            if (! $ok) {
                $failedOwners++;
            }
        }

        if ($failedOwners > 0) {
            $this->error("{$failedOwners} owner(s) did not reach a complete migration phase. Re-run this command to resume.");

            return self::FAILURE;
        }

        $this->info('Migration complete for '.count($userIds).' owner(s).');

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function resolveOwnerIds(): array
    {
        $explicit = array_values(array_map('intval', (array) $this->option('user')));
        if ($explicit !== []) {
            return $explicit;
        }
        if (! $this->option('all-owners')) {
            $this->error('Pass --all-owners or one or more --user=<id>.');

            return [];
        }

        $fromInvoices = DB::table('invoices')->distinct()->pluck('user_id');
        $fromTransactions = DB::table('bank_transactions')->whereNotNull('invoice_id')->distinct()->pluck('user_id');

        /** @var list<int> $ids */
        $ids = $fromInvoices->merge($fromTransactions)
            ->map(self::intOrFail(...))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids;
    }

    private function migrateOwner(int $ownerId, LegacyInvoiceMigration $invoices, LegacyPaymentLinkMigration $payments): bool
    {
        if (! User::query()->whereKey($ownerId)->exists()) {
            $this->warn("Owner {$ownerId} does not exist — skipping.");

            return true;
        }

        $checkpoint = DB::table('finance_invoice_migration_checkpoints')->where('user_id', $ownerId)->first();
        $phase = is_object($checkpoint) && is_string($checkpoint->phase ?? null) ? $checkpoint->phase : 'originals';
        $lastInvoiceId = is_object($checkpoint) ? self::nullableInt($checkpoint->last_invoice_id ?? null) : null;
        $lastTransactionId = is_object($checkpoint) ? self::nullableInt($checkpoint->last_bank_transaction_id ?? null) : null;

        $this->saveCheckpoint($ownerId, $phase, $lastInvoiceId, $lastTransactionId, 'running', null);
        $totalFailed = [];

        // Bounded loop: one iteration per chunk, capped generously so a pathological
        // state machine bug cannot spin forever instead of surfacing as a failure.
        for ($iteration = 0; $iteration < 1_000_000; $iteration++) {
            if (in_array($phase, ['originals', 'cancellations'], true)) {
                $result = $invoices->migrateChunk($ownerId, $phase, $lastInvoiceId);
                $phase = $result['phase'];
                $lastInvoiceId = $result['last_invoice_id'];
                foreach ($result['failed'] as $failure) {
                    $totalFailed[] = 'invoice#'.$failure['id'].': '.$failure['error'];
                }
                $this->saveCheckpoint($ownerId, $phase, $lastInvoiceId, $lastTransactionId, 'running', null);
                if ($result['done']) {
                    $phase = 'bank_links';
                    $lastTransactionId = null;
                }

                continue;
            }

            if (in_array($phase, ['bank_links', 'paid_markers'], true)) {
                $result = $payments->migrateChunk($ownerId, $phase, $phase === 'bank_links' ? $lastTransactionId : $lastInvoiceId);
                $phase = $result['phase'];
                if ($phase === 'paid_markers' || $phase === 'complete') {
                    $lastInvoiceId = $result['last_id'];
                } else {
                    $lastTransactionId = $result['last_id'];
                }
                foreach ($result['failed'] as $failure) {
                    $totalFailed[] = 'payment-link/invoice#'.$failure['id'].': '.$failure['error'];
                }
                $this->saveCheckpoint($ownerId, $phase, $lastInvoiceId, $lastTransactionId, 'running', null);

                continue;
            }

            break;
        }

        $this->syncSequenceCounters($ownerId);

        if ($totalFailed !== []) {
            $summary = implode('; ', array_slice($totalFailed, 0, 10));
            $this->warn("Owner {$ownerId}: ".count($totalFailed)." row(s) failed to migrate: {$summary}");
            $this->saveCheckpoint($ownerId, $phase, $lastInvoiceId, $lastTransactionId, 'failed', $summary);

            return false;
        }

        $this->saveCheckpoint($ownerId, 'complete', null, null, 'complete', null);
        $this->line("Owner {$ownerId}: migration complete.");

        return true;
    }

    /**
     * Preserves gapless future numbering: a legacy row was imported with its
     * exact historical (year, sequence); the live LockedInvoiceNumberAllocator
     * must continue from the highest one it now knows about, per year, or the
     * very next live finalization would collide with an imported number.
     */
    private function syncSequenceCounters(int $ownerId): void
    {
        $years = DB::table('finance_invoices')
            ->where('user_id', $ownerId)
            ->whereNotNull('year')
            ->select('year', DB::raw('MAX(sequence) as max_sequence'))
            ->groupBy('year')
            ->get();

        foreach ($years as $row) {
            $year = self::intOrFail($row->year ?? null);
            $maxSequence = $row->max_sequence ?? null;
            $next = ($maxSequence === null ? 0 : self::intOrFail($maxSequence)) + 1;
            $existing = DB::table('finance_invoice_sequences')
                ->where('user_id', $ownerId)
                ->where('series_key', 'invoice')
                ->where('year', $year)
                ->first();
            if ($existing === null) {
                DB::table('finance_invoice_sequences')->insert([
                    'user_id' => $ownerId,
                    'series_key' => 'invoice',
                    'year' => $year,
                    'next_sequence' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif (self::intOrFail($existing->next_sequence) < $next) {
                DB::table('finance_invoice_sequences')
                    ->where('id', self::intOrFail($existing->id))
                    ->update(['next_sequence' => $next, 'updated_at' => now()]);
            }
        }
    }

    private static function intOrFail(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new LogicException('Expected a numeric value.');
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : self::intOrFail($value);
    }

    private function saveCheckpoint(
        int $ownerId,
        string $phase,
        ?int $lastInvoiceId,
        ?int $lastTransactionId,
        string $status,
        ?string $errorDetail,
    ): void {
        DB::table('finance_invoice_migration_checkpoints')->updateOrInsert(
            ['user_id' => $ownerId],
            [
                'phase' => $phase,
                'last_invoice_id' => $lastInvoiceId,
                'last_bank_transaction_id' => $lastTransactionId,
                'status' => $status,
                'error_code' => $status === 'failed' ? 'legacy_invoice_migration_row_failed' : null,
                'error_detail' => $errorDetail !== null ? mb_substr($errorDetail, 0, 65000) : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
