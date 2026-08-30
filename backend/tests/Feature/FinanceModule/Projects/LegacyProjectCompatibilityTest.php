<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\BankTransaction;
use App\Models\FileEntry;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FinanceProjectTask;
use App\Models\FinanceQuote;
use App\Models\FinanceReceipt;
use App\Models\FinanceTimeEntry;
use App\Models\GalleryPhoto;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LegacyProjectCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_project_crud_preserves_parentage_and_rejects_cycles(): void
    {
        $this->signIn();
        $parentId = (int) $this->postJson(route('api.finance.projects.store'), [
            'name' => 'Parent', 'kind' => 'business',
        ])->assertCreated()->json('project.id');
        $childId = (int) $this->postJson(route('api.finance.projects.store'), [
            'name' => 'Child', 'kind' => 'private', 'parent_id' => $parentId,
        ])->assertCreated()->json('project.id');
        $grandchildId = (int) $this->postJson(route('api.finance.projects.store'), [
            'name' => 'Grandchild', 'kind' => 'private', 'parent_id' => $childId,
        ])->assertCreated()->json('project.id');

        $this->putJson(route('api.finance.projects.update', $childId), [
            'name' => 'Renamed child', 'version' => 0,
        ])->assertOk()->assertJsonPath('project.version', 1);

        $this->assertSame($parentId, (int) FinanceProject::query()->findOrFail($childId)->parent_id);
        $this->postJson(route('api.finance.projects.move', $parentId), ['parent_id' => $grandchildId])
            ->assertStatus(422)
            ->assertJsonPath('error', 'cycle');
        $this->assertNull(FinanceProject::query()->findOrFail($parentId)->parent_id);
    }

    public function test_all_five_legacy_project_statuses_are_accepted(): void
    {
        $this->signIn();

        foreach (['planned', 'active', 'on_hold', 'done', 'cancelled'] as $status) {
            $this->postJson(route('api.finance.projects.store'), [
                'name' => ucfirst($status), 'kind' => 'business', 'status' => $status,
            ])->assertCreated()->assertJsonPath('project.status', $status);
        }

        $this->postJson(route('api.finance.projects.store'), [
            'name' => 'Invalid', 'kind' => 'business', 'status' => 'archived',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_project_updates_reject_stale_optimistic_versions(): void
    {
        $this->signIn();
        $project = $this->project();

        $this->putJson(route('api.finance.projects.update', $project), [
            'name' => 'First writer', 'version' => 0,
        ])->assertOk()->assertJsonPath('project.version', 1);

        $this->putJson(route('api.finance.projects.update', $project), [
            'name' => 'Stale writer', 'version' => 0,
        ])->assertConflict()
            ->assertJsonPath('error', 'version_conflict')
            ->assertJsonPath('version', 1);

        $this->assertSame('First writer', (string) $project->fresh()?->name);
    }

    public function test_quote_conversion_creates_tasks_only_for_nonempty_service_lines(): void
    {
        $this->signIn();
        $quote = $this->quote([
            'title' => 'Compatibility quote',
            'net' => 450.75,
            'lines' => [
                ['desc' => "Consulting\nOn site", 'qty' => 2.5, 'kind' => 'service', 'productId' => null],
                ['desc' => 'Switch', 'qty' => 1, 'kind' => 'hardware', 'productId' => null],
                ['desc' => '   ', 'qty' => 4, 'kind' => 'service', 'productId' => null],
            ],
        ]);

        $projectId = (int) $this->postJson(route('api.finance.quotes.project', $quote))
            ->assertCreated()
            ->assertJsonPath('project.status', 'planned')
            ->json('project.id');

        $tasks = FinanceProjectTask::query()->where('finance_project_id', $projectId)->get();
        $this->assertCount(1, $tasks);
        $this->assertSame('Consulting', $tasks[0]->title);
        $this->assertSame('On site', $tasks[0]->description);
        $this->assertSame('2.50', (string) $tasks[0]->estimate_hours);
        $this->assertSame($projectId, (int) $quote->fresh()?->converted_project_id);
    }

    public function test_time_entry_freezes_the_partner_rate_at_log_time(): void
    {
        $this->signIn();
        $partner = FinancePartner::create([
            'name' => 'Rate partner', 'kind' => 'customer', 'hourly_rate' => 120,
        ]);
        $project = $this->project(['partner_id' => $partner->id]);

        $entryId = (int) $this->postJson(route('api.finance.projects.time.store', $project), [
            'hours' => 2,
        ])->assertCreated()->assertJsonPath('entry.hourly_rate', '120.00')->json('entry.id');

        $partner->forceFill(['hourly_rate' => 175])->save();

        $this->assertSame('120.00', (string) FinanceTimeEntry::query()->findOrFail($entryId)->hourly_rate);
    }

    public function test_deleting_a_task_detaches_but_preserves_its_time_entries(): void
    {
        $this->signIn();
        $project = $this->project();
        $taskId = (int) $this->postJson(route('api.finance.projects.tasks.store', $project), [
            'title' => 'Temporary task',
        ])->assertCreated()->json('task.id');
        $entryId = (int) $this->postJson(route('api.finance.projects.time.store', $project), [
            'finance_project_task_id' => $taskId, 'hours' => 1.5,
        ])->assertCreated()->json('entry.id');

        $this->deleteJson(route('api.finance.project-tasks.destroy', $taskId))->assertOk();

        $this->assertSoftDeleted('finance_project_tasks', ['id' => $taskId]);
        $this->assertNull(FinanceTimeEntry::query()->findOrFail($entryId)->finance_project_task_id);
        $this->assertSame('1.50', (string) FinanceTimeEntry::query()->findOrFail($entryId)->hours);
    }

    public function test_invoice_time_groups_by_rate_and_locks_every_consumed_entry(): void
    {
        $this->signIn();
        $project = $this->project();
        foreach ([[3, 120], [2.5, 120], [4, 95]] as [$hours, $rate]) {
            $this->postJson(route('api.finance.projects.time.store', $project), [
                'hours' => $hours, 'hourly_rate' => $rate,
            ])->assertCreated();
        }
        $keptId = (int) $this->postJson(route('api.finance.projects.time.store', $project), [
            'hours' => 1, 'hourly_rate' => 120, 'billable' => false,
        ])->assertCreated()->json('entry.id');

        $response = $this->postJson(route('api.finance.projects.invoice-time', $project))
            ->assertCreated()
            ->assertJsonPath('entries', 3);
        $invoiceId = (int) $response->json('invoice.id');
        $lines = collect($response->json('invoice.lines'))->keyBy(
            static fn (array $line): string => (string) $line['unitPrice'],
        );

        $this->assertCount(2, $lines);
        $this->assertSame(4.0, (float) $lines['95']['qty']);
        $this->assertSame(5.5, (float) $lines['120']['qty']);
        $this->assertSame(3, FinanceTimeEntry::query()->where('invoiced_finance_invoice_id', $invoiceId)->count());
        $this->assertNull(FinanceTimeEntry::query()->findOrFail($keptId)->invoiced_finance_invoice_id);

        $locked = FinanceTimeEntry::query()->where('invoiced_finance_invoice_id', $invoiceId)->firstOrFail();
        $this->putJson(route('api.finance.time-entries.update', $locked), ['hours' => 9])
            ->assertUnprocessable()->assertJsonPath('error', 'time_invoiced');
        $this->deleteJson(route('api.finance.time-entries.destroy', $locked))
            ->assertUnprocessable()->assertJsonPath('error', 'time_invoiced');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(1, DB::table('finance_invoices')->where('id', $invoiceId)->count());
    }

    public function test_generic_project_update_can_rewrite_status_note_and_the_whole_json_ledger(): void
    {
        $this->signIn();
        $projectId = (int) $this->postJson(route('api.finance.projects.store'), [
            'name' => 'Mutable v1 project',
            'kind' => 'business',
            'status' => 'planned',
            'note' => 'Original note',
            'expenses' => [
                ['id' => 'old-a', 'amount' => 10.25, 'category' => 'Travel'],
                ['id' => 'old-b', 'amount' => 4.75, 'unknown' => 'retained only in this blob'],
            ],
        ])->assertCreated()->json('project.id');

        $replacement = [['id' => 'new', 'amount' => 99.5, 'category' => 'Office']];
        $this->putJson(route('api.finance.projects.update', $projectId), [
            'status' => 'done',
            'note' => 'Replacement note',
            'expenses' => $replacement,
            'version' => 0,
        ])->assertOk()
            ->assertJsonPath('project.status', 'done')
            ->assertJsonPath('project.note', 'Replacement note')
            ->assertJsonCount(1, 'project.expenses');

        $project = FinanceProject::query()->findOrFail($projectId);
        $this->assertSame('done', $project->status);
        $this->assertSame('Replacement note', $project->note);
        $this->assertSame($replacement, $project->expenses);
        $this->assertStringNotContainsString('old-a', (string) $project->getRawOriginal('expenses'));
    }

    public function test_legacy_budget_and_time_are_two_decimal_float_shaped_values(): void
    {
        $this->signIn();
        $projectId = (int) $this->postJson(route('api.finance.projects.store'), [
            'name' => 'Rounded values', 'kind' => 'business', 'budget_net' => 12.345,
        ])->assertCreated()->json('project.id');
        $entryId = (int) $this->postJson(route('api.finance.projects.time.store', $projectId), [
            'hours' => 0.125, 'hourly_rate' => 99.999,
        ])->assertCreated()->json('entry.id');

        $this->assertSame('12.35', (string) FinanceProject::query()->findOrFail($projectId)->budget_net);
        $this->assertSame('0.13', (string) FinanceTimeEntry::query()->findOrFail($entryId)->hours);
        $this->assertSame('100.00', (string) FinanceTimeEntry::query()->findOrFail($entryId)->hourly_rate);

        $worked = $this->getJson(route('api.finance.projects.plan', $projectId))
            ->assertOk()->json('totals.worked_hours');
        $this->assertIsFloat($worked);
        $this->assertSame(0.13, $worked);
    }

    public function test_file_and_photo_assignment_mutates_the_owning_rows_without_link_history(): void
    {
        $owner = $this->signIn();
        $first = $this->project(['name' => 'First']);
        $second = $this->project(['name' => 'Second']);
        $file = FileEntry::forceCreate([
            'user_id' => $owner->id, 'name' => 'contract.pdf', 'storage_path' => 'files/'.Str::uuid(),
            'mime' => 'application/pdf', 'size' => 12, 'sha256' => str_repeat('a', 64), 'version' => 0,
        ]);
        $photo = GalleryPhoto::forceCreate([
            'user_id' => $owner->id, 'name' => 'site.jpg', 'storage_path' => 'gallery/'.Str::uuid(),
            'mime' => 'image/jpeg', 'size' => 34, 'sha256' => str_repeat('b', 64), 'version' => 0,
        ]);

        $this->putJson(route('files.rel.update', $file), [
            'finance_project_id' => $first->id, 'version' => 0,
        ])->assertOk();
        $this->putJson(route('gallery.update', $photo), [
            'finance_project_id' => $first->id, 'version' => 0,
        ])->assertOk();
        $this->putJson(route('files.rel.update', $file), [
            'finance_project_id' => $second->id, 'version' => 1,
        ])->assertOk();
        $this->putJson(route('gallery.update', $photo), [
            'finance_project_id' => $second->id, 'version' => 1,
        ])->assertOk();

        $this->getJson(route('api.finance.projects.attachments', $first))
            ->assertOk()->assertJsonCount(0, 'files')->assertJsonCount(0, 'photos');
        $this->getJson(route('api.finance.projects.attachments', $second))
            ->assertOk()->assertJsonCount(1, 'files')->assertJsonCount(1, 'photos');
        $this->assertSame($second->id, FileEntry::query()->findOrFail($file->id)->finance_project_id);
        $this->assertSame($second->id, GalleryPhoto::query()->findOrFail($photo->id)->finance_project_id);
        $this->assertSame(1, FileEntry::query()->count());
        $this->assertSame(1, GalleryPhoto::query()->count());
    }

    public function test_receipt_and_transaction_store_mutable_owner_scoped_project_pointers(): void
    {
        $owner = $this->signIn();
        $project = $this->project();
        $method = PaymentMethod::create(['type' => 'bank', 'name' => 'Main']);

        $transactionId = (int) $this->postJson(route('api.finance.transactions.store'), [
            'payment_method_id' => $method->id,
            'date' => '2026-08-28',
            'amount' => -42.25,
            'finance_project_id' => $project->id,
        ])->assertCreated()->json('transaction.id');
        $receipt = FinanceReceipt::forceCreate([
            'user_id' => $owner->id,
            'blob_path' => 'invoices/legacy-compatibility',
            'name' => 'receipt.pdf',
            'mime' => 'application/pdf',
            'size' => 12,
            'kind' => 'receipt',
            'version' => 0,
        ]);
        $this->putJson(route('api.finance.receipts.update', $receipt), [
            'finance_project_id' => $project->id, 'version' => 0,
        ])->assertOk()->assertJsonPath('receipt.finance_project_id', $project->id);

        $this->assertSame($project->id, BankTransaction::query()->findOrFail($transactionId)->finance_project_id);
        $this->assertSame($project->id, $receipt->fresh()?->finance_project_id);
        $this->getJson(route('api.finance.data'))->assertOk()
            ->assertJsonPath('transactions.0.finance_project_id', $project->id)
            ->assertJsonPath('standaloneReceipts.0.finance_project_id', $project->id);
    }

    public function test_file_photo_receipt_and_transaction_reject_a_foreign_project_pointer_without_mutation(): void
    {
        $owner = $this->signIn();
        $ownProject = $this->project(['name' => 'Owned project']);
        $file = FileEntry::forceCreate([
            'user_id' => $owner->id, 'finance_project_id' => $ownProject->id,
            'name' => 'owned.pdf', 'storage_path' => 'files/'.Str::uuid(),
            'mime' => 'application/pdf', 'size' => 12, 'sha256' => str_repeat('c', 64), 'version' => 0,
        ]);
        $photo = GalleryPhoto::forceCreate([
            'user_id' => $owner->id, 'finance_project_id' => $ownProject->id,
            'name' => 'owned.jpg', 'storage_path' => 'gallery/'.Str::uuid(),
            'mime' => 'image/jpeg', 'size' => 34, 'sha256' => str_repeat('d', 64), 'version' => 0,
        ]);
        $method = PaymentMethod::create(['type' => 'bank', 'name' => 'Owned method']);
        $transaction = BankTransaction::create([
            'payment_method_id' => $method->id, 'date' => '2026-08-28', 'amount' => -10,
            'finance_project_id' => $ownProject->id,
        ]);
        $receipt = FinanceReceipt::forceCreate([
            'user_id' => $owner->id, 'finance_project_id' => $ownProject->id,
            'blob_path' => 'invoices/foreign-project-reject', 'name' => 'owned-receipt.pdf',
            'size' => 1, 'kind' => 'receipt', 'version' => 0,
        ]);

        app('auth')->forgetGuards();
        $foreign = $this->signIn(User::factory()->create());
        $foreignProject = $this->project(['name' => 'Foreign project']);
        app('auth')->forgetGuards();
        $this->signIn($owner);

        $this->putJson(route('files.rel.update', $file), [
            'finance_project_id' => $foreignProject->id, 'version' => 0,
        ])->assertInvalid(['finance_project_id']);
        $this->putJson(route('gallery.update', $photo), [
            'finance_project_id' => $foreignProject->id, 'version' => 0,
        ])->assertInvalid(['finance_project_id']);
        $this->putJson(route('api.finance.transactions.update', $transaction), [
            'finance_project_id' => $foreignProject->id, 'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('finance_project_id');
        $this->putJson(route('api.finance.receipts.update', $receipt), [
            'finance_project_id' => $foreignProject->id, 'version' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('finance_project_id');

        $this->assertSame($ownProject->id, FileEntry::query()->findOrFail($file->id)->finance_project_id);
        $this->assertSame(0, FileEntry::query()->findOrFail($file->id)->version);
        $this->assertSame($ownProject->id, GalleryPhoto::query()->findOrFail($photo->id)->finance_project_id);
        $this->assertSame(0, GalleryPhoto::query()->findOrFail($photo->id)->version);
        $this->assertSame($ownProject->id, BankTransaction::query()->findOrFail($transaction->id)->finance_project_id);
        $this->assertSame(0, BankTransaction::query()->findOrFail($transaction->id)->version);
        $this->assertSame($ownProject->id, FinanceReceipt::query()->findOrFail($receipt->id)->finance_project_id);
        $this->assertSame(0, FinanceReceipt::query()->findOrFail($receipt->id)->version);
        $this->assertSame($foreign->id, $foreignProject->user_id);
    }

    public function test_foreign_project_graph_routes_resolve_as_not_found(): void
    {
        $owner = $this->signIn();
        $project = $this->project();
        $task = FinanceProjectTask::create([
            'finance_project_id' => $project->id, 'title' => 'Owned task', 'status' => 'open',
        ]);
        $entry = FinanceTimeEntry::create([
            'finance_project_id' => $project->id, 'finance_project_task_id' => $task->id,
            'date' => '2026-08-28', 'hours' => 1, 'billable' => true,
        ]);
        $method = PaymentMethod::create(['type' => 'bank', 'name' => 'Owned bank']);
        $transaction = BankTransaction::create([
            'payment_method_id' => $method->id, 'date' => '2026-08-28', 'amount' => -1,
        ]);
        $receipt = FinanceReceipt::forceCreate([
            'user_id' => $owner->id, 'blob_path' => 'invoices/owner-compatibility',
            'name' => 'owned.pdf', 'size' => 1, 'kind' => 'receipt', 'version' => 0,
        ]);

        app('auth')->forgetGuards();
        $this->signIn(User::factory()->create());

        $this->getJson(route('api.finance.projects.plan', $project))->assertNotFound();
        $this->getJson(route('api.finance.projects.attachments', $project))->assertNotFound();
        $this->putJson(route('api.finance.projects.update', $project), ['name' => 'stolen'])->assertNotFound();
        $this->putJson(route('api.finance.project-tasks.update', $task), ['title' => 'stolen'])->assertNotFound();
        $this->putJson(route('api.finance.time-entries.update', $entry), ['hours' => 2])->assertNotFound();
        $this->putJson(route('api.finance.transactions.update', $transaction), ['amount' => -2])->assertNotFound();
        $this->putJson(route('api.finance.receipts.update', $receipt), ['name' => 'stolen'])->assertNotFound();
    }

    public function test_project_trash_restore_and_force_delete_are_separate_operations(): void
    {
        $this->signIn();
        $project = $this->project();

        $this->deleteJson(route('api.finance.projects.destroy', $project))->assertOk();
        $this->assertSoftDeleted('finance_projects', ['id' => $project->id]);
        $this->getJson(route('api.finance.trash'))->assertOk()
            ->assertJsonPath('projects.0.id', $project->id);

        $this->postJson(route('api.finance.projects.restore', $project->id))
            ->assertOk()->assertJsonPath('project.deleted_at', null);
        $this->deleteJson(route('api.finance.projects.destroy', $project->id))->assertOk();
        $this->deleteJson(route('api.finance.projects.force', $project->id))->assertOk();

        $this->assertNull(FinanceProject::withTrashed()->find($project->id));
    }

    public function test_project_detail_is_only_present_inside_the_global_finance_snapshot(): void
    {
        $this->signIn();
        $first = $this->project(['name' => 'Alpha']);
        $second = $this->project(['name' => 'Beta']);

        $response = $this->getJson(route('api.finance.data'))->assertOk()->assertJsonCount(2, 'projects');
        $this->assertSame([$first->id, $second->id], collect($response->json('projects'))->pluck('id')->sort()->values()->all());
        $this->assertFalse(app('router')->has('api.finance.projects.show'));
    }

    public function test_legacy_project_detail_endpoints_enforce_their_500_and_1000_row_caps(): void
    {
        $owner = $this->signIn();
        $project = $this->project();
        $now = now();

        $taskRows = [];
        $timeRows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $taskRows[] = [
                'user_id' => $owner->id, 'finance_project_id' => $project->id,
                'title' => "Task {$i}", 'status' => 'open', 'is_milestone' => false,
                'sort' => $i, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
            ];
            $timeRows[] = [
                'user_id' => $owner->id, 'finance_project_id' => $project->id,
                'finance_project_task_id' => null, 'date' => '2026-08-28', 'hours' => 1,
                'billable' => true, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($taskRows, 250) as $chunk) {
            DB::table('finance_project_tasks')->insert($chunk);
        }
        foreach (array_chunk($timeRows, 250) as $chunk) {
            DB::table('finance_time_entries')->insert($chunk);
        }

        $fileRows = [];
        $photoRows = [];
        for ($i = 1; $i <= 501; $i++) {
            $fileRows[] = [
                'user_id' => $owner->id, 'finance_project_id' => $project->id,
                'name' => "File {$i}", 'storage_path' => "files/cap-{$i}",
                'size' => 1, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
            ];
            $photoRows[] = [
                'user_id' => $owner->id, 'finance_project_id' => $project->id,
                'name' => "Photo {$i}", 'storage_path' => "gallery/cap-{$i}",
                'size' => 1, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($fileRows, 250) as $chunk) {
            DB::table('files')->insert($chunk);
        }
        foreach (array_chunk($photoRows, 250) as $chunk) {
            DB::table('gallery_photos')->insert($chunk);
        }

        $plan = $this->getJson(route('api.finance.projects.plan', $project))->assertOk();
        $this->assertCount(1000, $plan->json('tasks'));
        $this->assertCount(1000, $plan->json('entries'));
        $this->assertSame(1000, $plan->json('totals.tasks'));

        $attachments = $this->getJson(route('api.finance.projects.attachments', $project))->assertOk();
        $this->assertCount(500, $attachments->json('files'));
        $this->assertCount(500, $attachments->json('photos'));
    }

    /** @param array<string, mixed> $attributes */
    private function project(array $attributes = []): FinanceProject
    {
        return FinanceProject::create(array_merge([
            'name' => 'Legacy project', 'kind' => 'business', 'status' => 'planned',
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function quote(array $attributes): FinanceQuote
    {
        $quote = new FinanceQuote;
        $quote->fill(array_merge([
            'title' => 'Legacy quote',
            'issue_date' => '2026-08-28',
            'customer' => ['name' => 'Customer'],
            'lines' => [],
            'net' => 0,
            'vat' => 0,
            'gross' => 0,
        ], $attributes));
        $quote->save();

        return $quote;
    }
}
