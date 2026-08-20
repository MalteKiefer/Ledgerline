<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Security/integrity hardening for the finance module:
 *  - GoBD: a trashed numbered invoice's number can NEVER be reused (the seq-max
 *    query counts soft-deleted numbered rows via withTrashed).
 *  - Restore of a numbered invoice fails gracefully (422) instead of 500 when the
 *    number now collides with a live invoice.
 *  - IDOR: a client-supplied versions[].pdf blob path is dropped — it can neither
 *    be streamed (arbitrary read) nor force-deleted (cross-invoice data loss).
 */
class FinanceSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function numberingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        UserSetting::for((int) $user->id)->update(['invoice_number_format' => 'YYYY-NNNN', 'invoice_next_number' => 1]);

        return $user;
    }

    public function test_trashing_a_numbered_invoice_does_not_free_its_number_for_reuse(): void
    {
        $this->numberingUser();

        $a = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-05-01'])->json('invoice.id');
        $this->assertSame('2026-0001', $this->postJson(route('finance.invoices.finalize', $a))->assertOk()->json('invoice.number'));

        // Trash the numbered invoice.
        $this->deleteJson(route('finance.invoices.destroy', $a))->assertOk();
        $this->assertSame(1, Invoice::onlyTrashed()->count());

        // A new invoice finalised in the same year must NOT reuse 2026-0001.
        $b = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-05-02'])->json('invoice.id');
        $nb = $this->postJson(route('finance.invoices.finalize', $b))->assertOk()->json('invoice.number');
        $this->assertSame('2026-0002', $nb);

        // The number is unique across the trash boundary (no reuse anywhere).
        $all = Invoice::withTrashed()->whereNotNull('number')->pluck('number')->all();
        $this->assertSame($all, array_unique($all));
        $this->assertEqualsCanonicalizing(['2026-0001', '2026-0002'], $all);
    }

    public function test_restore_refuses_when_the_number_now_collides_with_a_live_invoice(): void
    {
        $this->numberingUser();

        // Legacy state (pre-fix): a trashed numbered invoice whose number was later
        // taken by a live invoice. The partial unique index excludes the trashed row,
        // so both can exist — restoring must be refused, not 500 on the index.
        $x = Invoice::forceCreate(['number' => '2026-0009', 'seq' => 9, 'year' => 2026, 'status' => 'sent', 'issue_date' => '2026-01-01', 'currency' => 'EUR']);
        $x->delete();
        Invoice::forceCreate(['number' => '2026-0009', 'seq' => 9, 'year' => 2026, 'status' => 'sent', 'issue_date' => '2026-01-02', 'currency' => 'EUR']);

        $this->postJson(route('finance.invoices.restore', $x->id))
            ->assertStatus(422)
            ->assertJsonPath('error', 'number_conflict');

        $this->assertSame(1, Invoice::onlyTrashed()->count()); // still trashed, no crash
    }

    public function test_client_supplied_version_pdf_path_cannot_be_read_or_force_deleted(): void
    {
        $this->actingAs(User::factory()->create());

        // A blob that belongs to another invoice/user, under the shared invoices/ prefix
        // (so it passes safeBlobPath's prefix check — the real IDOR surface).
        $victim = 'invoices/victim-blob-uuid';
        Storage::disk(config('files.disk'))->put($victim, 'VICTIM PDF BYTES');

        $inv = $this->postJson(route('finance.invoices.store'), ['issue_date' => '2026-07-01'])->json('invoice.id');

        // Attacker points a version's pdf at the victim blob.
        $this->putJson(route('finance.invoices.update', $inv), [
            'version' => 0,
            'versions' => [['seq' => 1, 'label' => 'evil', 'pdf' => $victim]],
        ])->assertOk();

        // The client-supplied pdf path was stripped (never trusted).
        $versions = Invoice::findOrFail($inv)->versions ?? [];
        $this->assertCount(1, $versions);
        $this->assertArrayNotHasKey('pdf', $versions[0]);

        // Streaming that version does not serve the victim blob.
        $this->get(route('finance.invoices.pdf', $inv).'?version_seq=1')->assertNotFound();

        // Force-deleting the attacker's invoice must NOT delete the victim blob.
        $this->deleteJson(route('finance.invoices.destroy', $inv))->assertOk();
        $this->deleteJson(route('finance.invoices.force', $inv))->assertOk();
        Storage::disk(config('files.disk'))->assertExists($victim);
        $this->assertSame('VICTIM PDF BYTES', Storage::disk(config('files.disk'))->get($victim));
    }
}
