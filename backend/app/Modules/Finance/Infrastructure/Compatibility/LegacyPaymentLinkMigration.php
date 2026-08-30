<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\BankTransaction;
use App\Models\Invoice as LegacyInvoiceModel;
use App\Modules\Finance\Application\Commands\Payments\AllocatePayment;
use App\Modules\Finance\Application\Commands\Payments\RecordPayment;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationLineData;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Migrates `bank_transactions.invoice_id` links into the signed payment
 * ledger via the same RecordPayment/AllocatePayment an interactive user
 * would call, then — once every link for an owner is processed — closes any
 * legacy `status = 'paid'` invoice that still has an open balance with one
 * explicitly flagged `legacy_invoice_paid_marker` payment for exactly the
 * residual. That marker is never disguised as an imported bank transaction:
 * its `source_type` says plainly that no bank link accounts for the money.
 *
 * Each linked amount is allocated only up to the target invoice's current
 * open balance — a link for more than the invoice was ever short by leaves
 * the remainder correctly unapplied on the payment rather than throwing an
 * over-allocation error.
 */
final readonly class LegacyPaymentLinkMigration
{
    public const CHUNK_SIZE = 100;

    public function __construct(
        private RecordPayment $record,
        private AllocatePayment $allocate,
    ) {}

    /** @return array{phase: string, last_id: int|null, processed: int, failed: list<array{id:int, error:string}>, done: bool} */
    public function migrateChunk(int $ownerId, string $phase, ?int $afterId): array
    {
        return match ($phase) {
            'bank_links' => $this->migrateBankLinks($ownerId, $afterId),
            'paid_markers' => $this->migratePaidMarkers($ownerId, $afterId),
            default => ['phase' => 'complete', 'last_id' => null, 'processed' => 0, 'failed' => [], 'done' => true],
        };
    }

    /** @return array{phase: string, last_id: int|null, processed: int, failed: list<array{id:int, error:string}>, done: bool} */
    private function migrateBankLinks(int $ownerId, ?int $afterId): array
    {
        $rows = BankTransaction::withTrashed()
            ->where('user_id', $ownerId)
            ->whereNotNull('invoice_id')
            ->when($afterId !== null, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        $failed = [];
        $lastId = $afterId;
        foreach ($rows as $row) {
            $lastId = (int) $row->id;
            try {
                $this->importLink($ownerId, $row);
            } catch (Throwable $exception) {
                $failed[] = ['id' => (int) $row->id, 'error' => $exception->getMessage()];
            }
        }

        $done = $rows->count() < self::CHUNK_SIZE;

        return [
            'phase' => $done ? 'paid_markers' : 'bank_links',
            'last_id' => $done ? null : $lastId,
            'processed' => $rows->count(),
            'failed' => $failed,
            'done' => false,
        ];
    }

    /** @return array{phase: string, last_id: int|null, processed: int, failed: list<array{id:int, error:string}>, done: bool} */
    private function migratePaidMarkers(int $ownerId, ?int $afterId): array
    {
        $rows = LegacyInvoiceModel::withTrashed()
            ->where('user_id', $ownerId)
            ->where('status', 'paid')
            ->when($afterId !== null, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        $failed = [];
        $lastId = $afterId;
        foreach ($rows as $row) {
            $lastId = (int) $row->id;
            try {
                $this->closeResidual($ownerId, $row);
            } catch (Throwable $exception) {
                $failed[] = ['id' => (int) $row->id, 'error' => $exception->getMessage()];
            }
        }

        $done = $rows->count() < self::CHUNK_SIZE;

        return [
            'phase' => $done ? 'complete' : 'paid_markers',
            'last_id' => $done ? null : $lastId,
            'processed' => $rows->count(),
            'failed' => $failed,
            'done' => $done,
        ];
    }

    private function importLink(int $ownerId, BankTransaction $transaction): void
    {
        $legacyInvoiceId = $transaction->invoice_id;
        if (! is_int($legacyInvoiceId)) {
            return;
        }
        $newInvoiceId = DB::table('finance_invoices')
            ->where('user_id', $ownerId)
            ->where('source_type', 'legacy_invoice')
            ->where('source_key', (string) $legacyInvoiceId)
            ->value('id');
        if (! is_int($newInvoiceId)) {
            throw new DomainException('legacy_bank_transaction_invoice_not_migrated');
        }

        $currencyRaw = DB::table('finance_invoices as i')
            ->join('finance_document_revisions as r', function (JoinClause $join): void {
                $join->on('r.id', '=', 'i.current_revision_id')
                    ->on('r.document_series_id', '=', 'i.document_series_id')
                    ->on('r.user_id', '=', 'i.user_id');
            })
            ->where('i.id', $newInvoiceId)
            ->value('r.currency');
        if (! is_string($currencyRaw)) {
            throw new DomainException('legacy_bank_transaction_invoice_currency_unreadable');
        }
        $currency = $currencyRaw;

        $amountRaw = $transaction->amount;
        if (! is_string($amountRaw) && ! is_numeric($amountRaw)) {
            throw new DomainException('legacy_bank_transaction_amount_invalid');
        }
        $amountMinor = Money::fromDecimal((string) $amountRaw, $currency)->minor();
        if ($amountMinor === 0) {
            return;
        }

        $this->withOwner($ownerId, function () use ($ownerId, $transaction, $amountMinor, $currency, $newInvoiceId): void {
            $payment = $this->record->handle(
                new RecordPaymentData(
                    amountMinor: $amountMinor,
                    currency: $currency,
                    receivedAt: $transaction->date !== null
                        ? $transaction->date->toDateTimeImmutable()
                        : new DateTimeImmutable('@0'),
                    reference: is_string($transaction->eref) ? mb_substr($transaction->eref, 0, 255) : null,
                    counterparty: is_string($transaction->counterparty) ? mb_substr($transaction->counterparty, 0, 255) : null,
                    sourceType: 'bank_transaction',
                    sourceKey: (string) $transaction->id,
                ),
                new IdempotencyKey('legacy-bank-transaction-record:'.$transaction->id),
            );

            $openMinorRaw = DB::table('finance_invoices')
                ->where('user_id', $ownerId)
                ->where('id', $newInvoiceId)
                ->value('open_minor');
            if (! is_int($openMinorRaw) || $openMinorRaw === 0
                || ($openMinorRaw > 0) !== ($amountMinor > 0)) {
                return;
            }
            $allocateMinor = abs($amountMinor) <= abs($openMinorRaw) ? $amountMinor : $openMinorRaw;

            $this->allocate->handle(
                new AllocatePaymentData(
                    new PaymentId($payment->id->value),
                    [new AllocationLineData(new InvoiceId($newInvoiceId), $allocateMinor)],
                ),
                new IdempotencyKey('legacy-bank-transaction-allocate:'.$transaction->id),
            );
        });
    }

    private function closeResidual(int $ownerId, LegacyInvoiceModel $legacy): void
    {
        $newInvoiceId = DB::table('finance_invoices')
            ->where('user_id', $ownerId)
            ->where('source_type', 'legacy_invoice')
            ->where('source_key', (string) $legacy->id)
            ->value('id');
        if (! is_int($newInvoiceId)) {
            // Not migrated (e.g. failed earlier) — nothing to close here.
            return;
        }

        $row = DB::table('finance_invoices as i')
            ->join('finance_document_revisions as r', function (JoinClause $join): void {
                $join->on('r.id', '=', 'i.current_revision_id')
                    ->on('r.document_series_id', '=', 'i.document_series_id')
                    ->on('r.user_id', '=', 'i.user_id');
            })
            ->where('i.id', $newInvoiceId)
            ->first(['i.open_minor', 'r.currency']);
        if ($row === null) {
            return;
        }
        $openMinorRaw = $row->open_minor;
        $currencyRaw = $row->currency;
        if (! is_int($openMinorRaw) || $openMinorRaw === 0 || ! is_string($currencyRaw)) {
            return;
        }
        $currency = $currencyRaw;
        $residualMinor = $openMinorRaw;

        $this->withOwner($ownerId, function () use ($legacy, $currency, $residualMinor, $newInvoiceId): void {
            $payment = $this->record->handle(
                new RecordPaymentData(
                    amountMinor: $residualMinor,
                    currency: $currency,
                    receivedAt: $legacy->paid_at?->toDateTimeImmutable() ?? new DateTimeImmutable('@0'),
                    reference: null,
                    counterparty: null,
                    sourceType: 'legacy_invoice_paid_marker',
                    sourceKey: (string) $legacy->id,
                ),
                new IdempotencyKey('legacy-invoice-paid-marker-record:'.$legacy->id),
            );
            $this->allocate->handle(
                new AllocatePaymentData(
                    new PaymentId($payment->id->value),
                    [new AllocationLineData(new InvoiceId($newInvoiceId), $residualMinor)],
                ),
                new IdempotencyKey('legacy-invoice-paid-marker-allocate:'.$legacy->id),
            );
        });
    }

    private function withOwner(int $ownerId, callable $operation): void
    {
        $previousUser = Auth::user();
        Auth::onceUsingId($ownerId);
        try {
            $operation();
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
