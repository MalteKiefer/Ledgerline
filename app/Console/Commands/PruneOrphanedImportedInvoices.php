<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Removes imported invoices whose source PDF is no longer present.
 *
 * Before the file-delete cascade was fixed, deleting an imported invoice's file
 * could leave the invoice record behind. This command finds imported invoices
 * that have no surviving attached file and deletes them (dry-run by default).
 */
#[Signature('invoices:prune-orphaned {--force : Delete the orphans instead of only listing them}')]
#[Description('Find (and optionally delete) imported invoices whose source file is gone')]
class PruneOrphanedImportedInvoices extends Command
{
    public function handle(): int
    {
        $orphans = Invoice::withTrashed()
            ->whereNotNull('imported_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('files')
                    ->whereColumn('files.attachable_id', 'invoices.id')
                    ->where('files.attachable_type', Invoice::class);
            })
            ->orderBy('id')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned imported invoices found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$orphans->count()} imported invoice(s) with no source file:");
        $this->table(
            ['ID', 'Number', 'Issued', 'Gross'],
            $orphans->map(fn (Invoice $i): array => [
                $i->id,
                $i->number ?? '—',
                $i->issue_date?->format('Y-m-d') ?? '—',
                $i->gross()->format(),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->line('Re-run with --force to delete them.');

            return self::SUCCESS;
        }

        // Bypass the finalised-invoice immutability guard: a full delete is
        // allowed (delete fires no updating events).
        $count = 0;
        foreach ($orphans as $invoice) {
            $invoice->forceDelete();
            $count++;
        }

        $this->info("Deleted {$count} orphaned imported invoice(s).");

        return self::SUCCESS;
    }
}
