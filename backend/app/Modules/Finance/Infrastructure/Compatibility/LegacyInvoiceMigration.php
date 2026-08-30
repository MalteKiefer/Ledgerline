<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\Invoice as LegacyInvoiceModel;
use App\Modules\Finance\Application\Commands\Invoices\ImportLegacyInvoice;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Per-owner, resumable, chunked legacy invoice migration. Runs the exact
 * same ImportLegacyInvoice command a live caller would use — this class
 * only selects legacy rows in the right order and reports outcomes; it
 * never writes to a `finance_*` table directly.
 *
 * Two passes per owner, because a cancellation's credit document must be
 * imported after its original (createCancellationDraft-shaped source
 * requires resolving the already-migrated original's new invoice ID):
 * pass "originals" imports every row with cancels_invoice_id === null;
 * pass "cancellations" imports every row that has one, once its target has
 * already been migrated in the current or an earlier run.
 */
final readonly class LegacyInvoiceMigration
{
    public const CHUNK_SIZE = 100;

    public function __construct(
        private LegacyInvoiceMapper $mapper,
        private ImportLegacyInvoice $import,
    ) {}

    /**
     * Imports up to CHUNK_SIZE legacy invoices for one owner, starting after
     * the given checkpoint. Returns the new checkpoint state.
     *
     * @return array{phase: string, last_invoice_id: int|null, processed: int, failed: list<array{id:int, error:string}>, done: bool}
     */
    public function migrateChunk(int $ownerId, string $phase, ?int $afterLegacyId): array
    {
        return match ($phase) {
            'originals' => $this->migrateOriginals($ownerId, $afterLegacyId),
            'cancellations' => $this->migrateCancellations($ownerId, $afterLegacyId),
            default => ['phase' => 'complete', 'last_invoice_id' => null, 'processed' => 0, 'failed' => [], 'done' => true],
        };
    }

    /** @return array{phase: string, last_invoice_id: int|null, processed: int, failed: list<array{id:int, error:string}>, done: bool} */
    private function migrateOriginals(int $ownerId, ?int $afterLegacyId): array
    {
        $rows = LegacyInvoiceModel::withTrashed()
            ->where('user_id', $ownerId)
            ->whereNull('cancels_invoice_id')
            ->when($afterLegacyId !== null, fn ($q) => $q->where('id', '>', $afterLegacyId))
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        $failed = [];
        $lastId = $afterLegacyId;
        foreach ($rows as $row) {
            $lastId = (int) $row->id;
            try {
                $this->importOne($ownerId, $row, null);
            } catch (Throwable $exception) {
                $failed[] = ['id' => (int) $row->id, 'error' => $exception->getMessage()];
            }
        }

        $done = $rows->count() < self::CHUNK_SIZE;

        return [
            'phase' => $done ? 'cancellations' : 'originals',
            'last_invoice_id' => $done ? null : $lastId,
            'processed' => $rows->count(),
            'failed' => $failed,
            'done' => false,
        ];
    }

    /** @return array{phase: string, last_invoice_id: int|null, processed: int, failed: list<array{id:int, error:string}>, done: bool} */
    private function migrateCancellations(int $ownerId, ?int $afterLegacyId): array
    {
        $rows = LegacyInvoiceModel::withTrashed()
            ->where('user_id', $ownerId)
            ->whereNotNull('cancels_invoice_id')
            ->when($afterLegacyId !== null, fn ($q) => $q->where('id', '>', $afterLegacyId))
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        $failed = [];
        $lastId = $afterLegacyId;
        foreach ($rows as $row) {
            $lastId = (int) $row->id;
            $legacyOriginalId = $row->cancels_invoice_id;
            $newOriginalId = is_int($legacyOriginalId)
                ? DB::table('finance_invoices')
                    ->where('user_id', $ownerId)
                    ->where('source_type', 'legacy_invoice')
                    ->where('source_key', (string) $legacyOriginalId)
                    ->value('id')
                : null;
            if (! is_int($newOriginalId)) {
                $failed[] = ['id' => (int) $row->id, 'error' => 'legacy_invoice_cancellation_target_not_migrated'];

                continue;
            }
            try {
                $this->importOne($ownerId, $row, $newOriginalId);
            } catch (Throwable $exception) {
                $failed[] = ['id' => (int) $row->id, 'error' => $exception->getMessage()];
            }
        }

        $done = $rows->count() < self::CHUNK_SIZE;

        return [
            'phase' => $done ? 'complete' : 'cancellations',
            'last_invoice_id' => $done ? null : $lastId,
            'processed' => $rows->count(),
            'failed' => $failed,
            'done' => $done,
        ];
    }

    private function importOne(int $ownerId, LegacyInvoiceModel $row, ?int $newCancelsInvoiceId): void
    {
        ['source' => $source, 'finalization' => $finalization] = $this->mapper->map($row, $newCancelsInvoiceId);

        $previousUser = Auth::user();
        Auth::onceUsingId($ownerId);
        try {
            $this->import->handle(
                $source,
                new IdempotencyKey('legacy-invoice-draft:'.$row->id),
                $finalization,
                $finalization !== null ? new IdempotencyKey('legacy-invoice-finalize:'.$row->id) : null,
            );
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
