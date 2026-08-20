<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairHistoricInvoiceImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dry_runs_then_repairs_only_the_verified_historic_imports(): void
    {
        $user = $this->signIn();
        foreach ([['2025-3', 28], ['2025-6', 0]] as [$number, $rate]) {
            $invoice = Invoice::create(['status' => 'paid', 'imported' => true, 'gross' => 19, 'vat_rate' => $rate]);
            $invoice->forceFill(['number' => $number])->save();
        }

        $this->artisan('finance:repair-historic-imports', ['--user' => $user->id])->assertOk();
        $this->assertSame('19.00', Invoice::where('number', '2025-3')->value('gross'));

        $this->artisan('finance:repair-historic-imports', ['--user' => $user->id, '--apply' => true])->assertOk();
        $three = Invoice::where('number', '2025-3')->firstOrFail();
        $six = Invoice::where('number', '2025-6')->firstOrFail();
        $this->assertSame('92.49', $three->gross);
        $this->assertSame('173.26', $six->gross);
        $this->assertSame(1, $three->version);
        $this->assertSame(1, $six->version);
        $this->assertCount(1, $three->versions ?? []);
        $this->assertCount(1, $six->versions ?? []);
        $this->assertSame(2, AuditLog::where('action', 'finance.invoice.import_corrected')->count());

        $this->artisan('finance:repair-historic-imports', ['--user' => $user->id, '--apply' => true])->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'finance.invoice.import_corrected')->count());
    }
}
