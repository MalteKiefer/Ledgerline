<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraft;
use App\Modules\Finance\Application\Commands\Invoices\DeleteInvoiceDraft;
use App\Modules\Finance\Application\Commands\Invoices\UpdateInvoiceDraft;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\SystemClock;
use DateTimeImmutable;
use DomainException;
use Error;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use TypeError;

final class InvoiceDraftApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $clock = new SystemClock;
        $idempotency = new EloquentIdempotencyStore($clock);
        $this->app->instance(IdempotencyStore::class, $idempotency);
        $this->app->instance(
            InvoiceRepository::class,
            new EloquentInvoiceRepository($idempotency, $clock),
        );
    }

    public function test_create_uses_exact_server_totals_and_persists_a_canonical_snapshot(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $invoice = $this->app->make(CreateInvoiceDraft::class)->handle($this->draft());

        $this->assertSame(25_000, $invoice->netMinor);
        $this->assertSame(4_750, $invoice->vatMinor);
        $this->assertSame(29_750, $invoice->grossMinor);
        $this->assertSame(29_750, $invoice->openMinor);
        $this->assertSame([
            'customer' => [
                'address' => ['city' => 'Berlin', 'postcode' => '10115'],
                'name' => 'ACME',
            ],
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'currency' => 'EUR',
            'lines' => [[
                'description' => 'Work',
                'quantity' => '2.5000',
                'quantity_scaled' => 25_000,
                'unit_price_minor' => 10_000,
                'tax_rate_basis_points' => 1_900,
                'unit' => 'h',
                'product_id' => null,
                'kind' => 'service',
            ]],
            'discount' => [
                'basis_points' => 0,
                'fixed_minor' => 0,
                'currency' => 'EUR',
            ],
            'totals' => [
                'net_minor' => 25_000,
                'vat_minor' => 4_750,
                'gross_minor' => 29_750,
                'discount_minor' => 0,
                'currency' => 'EUR',
                'tax_breakdowns' => [[
                    'tax_rate_basis_points' => 1_900,
                    'net_minor' => 25_000,
                    'vat_minor' => 4_750,
                    'gross_minor' => 29_750,
                ]],
            ],
        ], $invoice->snapshot);
        $this->assertSame(['invoice.draft.created'], DB::table('finance_document_activities')
            ->where('user_id', $owner->id)
            ->pluck('type')
            ->all());
        $this->assertDatabaseHas('finance_invoices', [
            'id' => $invoice->id->value,
            'user_id' => $owner->id,
            'workflow_status' => 'draft',
            'number' => null,
            'finalized_at' => null,
        ]);
    }

    public function test_draft_input_rejects_a_float_anywhere_in_customer_snapshot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice customer data must contain JSON values without floats.');

        new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: [
                'name' => 'ACME',
                'meta' => ['unsafe' => 1.5],
            ],
            lines: [new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
        );
    }

    public function test_line_kind_is_canonical_and_cannot_be_a_behavior_switch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice line kind must be service, hardware, or null.');

        new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', null, 'consulting');
    }

    public function test_control_totals_are_checks_and_never_replace_server_totals(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('document_totals_mismatch');

        $this->app->make(CreateInvoiceDraft::class)->handle(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: 1,
            controlVatMinor: 2,
            controlGrossMinor: 3,
        ));
    }

    public function test_create_rejects_foreign_partner_project_and_product_references(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignPartnerId = (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign partner',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignProjectId = (int) DB::table('finance_projects')->insertGetId([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign project',
            'kind' => 'business',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignProductId = (int) DB::table('finance_products')->insertGetId([
            'user_id' => $otherOwner->id,
            'kind' => 'service',
            'name' => 'Foreign product',
            'price_net' => 100,
            'active' => true,
            'track_stock' => false,
            'stock_qty' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);

        foreach ([
            new InvoiceDraftData(
                new DateTimeImmutable('2026-08-28'),
                new DateTimeImmutable('2026-09-11'),
                'EUR',
                ['name' => 'ACME'],
                [new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', null, 'service')],
                Discount::none('EUR'),
                partnerId: $foreignPartnerId,
            ),
            new InvoiceDraftData(
                new DateTimeImmutable('2026-08-28'),
                new DateTimeImmutable('2026-09-11'),
                'EUR',
                ['name' => 'ACME'],
                [new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', null, 'service')],
                Discount::none('EUR'),
                projectId: $foreignProjectId,
            ),
            new InvoiceDraftData(
                new DateTimeImmutable('2026-08-28'),
                new DateTimeImmutable('2026-09-11'),
                'EUR',
                ['name' => 'ACME'],
                [new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', $foreignProductId, 'service')],
                Discount::none('EUR'),
            ),
        ] as $draft) {
            try {
                $this->app->make(CreateInvoiceDraft::class)->handle($draft);
                $this->fail('A foreign invoice reference resolved for the authenticated owner.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
    }

    public function test_create_returns_owned_partner_project_and_snapshotted_product_references(): void
    {
        $owner = User::factory()->create();
        $partnerId = (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Owned partner',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $projectId = (int) DB::table('finance_projects')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Owned project',
            'kind' => 'business',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = (int) DB::table('finance_products')->insertGetId([
            'user_id' => $owner->id,
            'kind' => 'service',
            'name' => 'Owned product',
            'price_net' => 100,
            'active' => true,
            'track_stock' => false,
            'stock_qty' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);

        $invoice = $this->app->make(CreateInvoiceDraft::class)->handle(new InvoiceDraftData(
            new DateTimeImmutable('2026-08-28'),
            new DateTimeImmutable('2026-09-11'),
            'EUR',
            ['name' => 'ACME'],
            [new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', $productId, 'service')],
            Discount::none('EUR'),
            partnerId: $partnerId,
            projectId: $projectId,
        ));

        $this->assertSame($partnerId, $invoice->partnerId);
        $this->assertSame($projectId, $invoice->projectId);
        $this->assertSame($productId, $invoice->snapshot['lines'][0]['product_id']);
    }

    public function test_update_uses_compare_and_set_and_appends_one_winner_activity(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateInvoiceDraft::class)->handle($this->draft());
        $command = $this->app->make(UpdateInvoiceDraft::class);
        $updatedData = new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-29'),
            dueDate: new DateTimeImmutable('2026-09-12'),
            currency: 'EUR',
            customer: ['name' => 'Updated ACME'],
            lines: [new InvoiceLineData('Updated work', '3.0000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: 30_000,
            controlVatMinor: 5_700,
            controlGrossMinor: 35_700,
        );

        $updated = $command->handle($created->id, 0, $updatedData);

        $this->assertSame(1, $updated->version);
        $this->assertSame(30_000, $updated->netMinor);
        $this->assertSame('Updated ACME', $updated->snapshot['customer']['name']);
        try {
            $command->handle($created->id, 0, $this->draft());
            $this->fail('A stale invoice draft update won its compare-and-set.');
        } catch (DomainException $exception) {
            $this->assertSame('invoice_version_conflict', $exception->getMessage());
        }
        $this->assertSame(
            ['invoice.draft.created', 'invoice.draft.updated'],
            DB::table('finance_document_activities')
                ->where('user_id', $owner->id)
                ->orderBy('id')
                ->pluck('type')
                ->all(),
        );
    }

    public function test_delete_removes_the_complete_unpublished_draft_aggregate_with_cas(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateInvoiceDraft::class)->handle($this->draft());
        $invoice = DB::table('finance_invoices')->where('id', $created->id->value)->first();
        $this->assertNotNull($invoice);

        $this->app->make(DeleteInvoiceDraft::class)->handle($created->id, 0);

        $this->assertDatabaseMissing('finance_invoices', ['id' => $created->id->value]);
        $this->assertDatabaseMissing('finance_document_revisions', ['id' => $invoice->current_revision_id]);
        $this->assertDatabaseMissing('finance_document_series', ['id' => $invoice->document_series_id]);
        $this->assertSame(0, DB::table('finance_document_activities')
            ->where('document_series_id', $invoice->document_series_id)
            ->count());
    }

    public function test_delete_rejects_stale_or_finalized_invoices_without_removing_history(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $stale = $this->app->make(CreateInvoiceDraft::class)->handle($this->draft());
        $finalized = $this->app->make(CreateInvoiceDraft::class)->handle($this->draft());
        DB::table('finance_invoices')->where('id', $finalized->id->value)->update([
            'workflow_status' => 'finalized',
            'finalized_at' => now(),
        ]);
        $command = $this->app->make(DeleteInvoiceDraft::class);

        try {
            $command->handle($stale->id, 1);
            $this->fail('A stale delete removed an invoice draft.');
        } catch (DomainException $exception) {
            $this->assertSame('invoice_version_conflict', $exception->getMessage());
        }
        try {
            $command->handle($finalized->id, 0);
            $this->fail('A finalized invoice was deleted through the draft command.');
        } catch (DomainException $exception) {
            $this->assertSame('invoice_not_deletable', $exception->getMessage());
        }

        $this->assertSame(2, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(2, DB::table('finance_document_revisions')->where('user_id', $owner->id)->count());
        $this->assertSame(2, DB::table('finance_document_activities')->where('user_id', $owner->id)->count());
    }

    public function test_update_rejects_foreign_references_without_mutating_snapshot_or_activity(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignPartnerId = (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign partner',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);
        $created = $this->app->make(CreateInvoiceDraft::class)->handle($this->draft());
        $invalid = new InvoiceDraftData(
            new DateTimeImmutable('2026-08-28'),
            new DateTimeImmutable('2026-09-11'),
            'EUR',
            ['name' => 'Changed'],
            [new InvoiceLineData('Changed', '1.0000', 10_000, 1_900, 'h', null, 'service')],
            Discount::none('EUR'),
            partnerId: $foreignPartnerId,
        );

        try {
            $this->app->make(UpdateInvoiceDraft::class)->handle($created->id, 0, $invalid);
            $this->fail('A foreign reference mutated an invoice draft.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $stored = $this->app->make(InvoiceRepository::class)->get($created->id);
        $this->assertSame(0, $stored->version);
        $this->assertSame('ACME', $stored->snapshot['customer']['name']);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('user_id', $owner->id)
            ->count());
    }

    public function test_typed_input_rejects_float_money_and_client_workflow_number_or_pdf_authority(): void
    {
        try {
            new InvoiceLineData('Work', '1.0000', 100.5, 1_900, 'h', null, 'service');
            $this->fail('A floating-point unit price crossed the minor-unit boundary.');
        } catch (TypeError) {
            $this->addToAssertionCount(1);
        }

        foreach (['workflowStatus' => 'sent', 'number' => 'INV-1', 'pdfPath' => 'invoice.pdf'] as $field => $value) {
            try {
                new InvoiceDraftData(...[
                    'issueDate' => new DateTimeImmutable('2026-08-28'),
                    'dueDate' => new DateTimeImmutable('2026-09-11'),
                    'currency' => 'EUR',
                    'customer' => ['name' => 'ACME'],
                    'lines' => [new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', null, 'service')],
                    'discount' => Discount::none('EUR'),
                    $field => $value,
                ]);
                $this->fail("Client field {$field} crossed the draft boundary.");
            } catch (Error) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function draft(): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: [
                'name' => 'ACME',
                'address' => ['postcode' => '10115', 'city' => 'Berlin'],
            ],
            lines: [new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: 25_000,
            controlVatMinor: 4_750,
            controlGrossMinor: 29_750,
        );
    }
}
