<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOrphanedImportedInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function importedInvoice(string $number = 'RE-2026-0001'): Invoice
    {
        return Invoice::factory()->create([
            'imported_at' => now(),
            'finalized_at' => now(),
            'number' => $number,
        ]);
    }

    public function test_dry_run_lists_but_keeps_orphans(): void
    {
        $orphan = $this->importedInvoice();

        $this->artisan('invoices:prune-orphaned')
            ->expectsOutputToContain('Found 1 imported invoice')
            ->assertSuccessful();

        $this->assertDatabaseHas('invoices', ['id' => $orphan->id]);
    }

    public function test_force_deletes_only_orphans(): void
    {
        $orphan = $this->importedInvoice();

        $withFile = $this->importedInvoice('RE-2026-0002');
        File::factory()->create(['attachable_type' => Invoice::class, 'attachable_id' => $withFile->id]);

        $this->artisan('invoices:prune-orphaned --force')->assertSuccessful();

        $this->assertDatabaseMissing('invoices', ['id' => $orphan->id]);
        $this->assertDatabaseHas('invoices', ['id' => $withFile->id]);
    }

    public function test_reports_when_none_found(): void
    {
        $this->artisan('invoices:prune-orphaned')
            ->expectsOutputToContain('No orphaned imported invoices found.')
            ->assertSuccessful();
    }
}
