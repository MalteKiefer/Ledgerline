<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\User;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_routes_are_exact_additive_uuid_scoped_and_protected(): void
    {
        $routes = [
            'api.finance-v2.projects.index' => ['GET', 'api/v1/finance-v2/projects'],
            'api.finance-v2.projects.store' => ['POST', 'api/v1/finance-v2/projects'],
            'api.finance-v2.projects.show' => ['GET', 'api/v1/finance-v2/projects/{project}'],
            'api.finance-v2.projects.update' => ['PUT', 'api/v1/finance-v2/projects/{project}'],
            'api.finance-v2.projects.status' => ['POST', 'api/v1/finance-v2/projects/{project}/status'],
            'api.finance-v2.projects.move' => ['POST', 'api/v1/finance-v2/projects/{project}/move'],
            'api.finance-v2.projects.archive' => ['DELETE', 'api/v1/finance-v2/projects/{project}'],
            'api.finance-v2.projects.restore' => ['POST', 'api/v1/finance-v2/projects/{project}/restore'],
            'api.finance-v2.projects.work-items.index' => ['GET', 'api/v1/finance-v2/projects/{project}/work-items'],
            'api.finance-v2.projects.work-items.store' => ['POST', 'api/v1/finance-v2/projects/{project}/work-items'],
            'api.finance-v2.projects.work-items.update' => ['PUT', 'api/v1/finance-v2/projects/{project}/work-items/{workItem}'],
            'api.finance-v2.projects.work-items.destroy' => ['DELETE', 'api/v1/finance-v2/projects/{project}/work-items/{workItem}'],
            'api.finance-v2.projects.work-items.reorder' => ['POST', 'api/v1/finance-v2/projects/{project}/work-items/reorder'],
            'api.finance-v2.projects.time-entries.index' => ['GET', 'api/v1/finance-v2/projects/{project}/time-entries'],
            'api.finance-v2.projects.time-entries.store' => ['POST', 'api/v1/finance-v2/projects/{project}/time-entries'],
            'api.finance-v2.projects.time-entries.update' => ['PUT', 'api/v1/finance-v2/projects/{project}/time-entries/{entry}'],
            'api.finance-v2.projects.time-entries.destroy' => ['DELETE', 'api/v1/finance-v2/projects/{project}/time-entries/{entry}'],
            'api.finance-v2.projects.invoice-drafts.store' => ['POST', 'api/v1/finance-v2/projects/{project}/invoice-drafts'],
            'api.finance-v2.projects.totals.show' => ['GET', 'api/v1/finance-v2/projects/{project}/totals'],
            'api.finance-v2.projects.ledger.index' => ['GET', 'api/v1/finance-v2/projects/{project}/ledger'],
            'api.finance-v2.projects.ledger.store' => ['POST', 'api/v1/finance-v2/projects/{project}/ledger'],
            'api.finance-v2.projects.ledger.update' => ['PUT', 'api/v1/finance-v2/projects/{project}/ledger/{entry}'],
            'api.finance-v2.projects.ledger.destroy' => ['DELETE', 'api/v1/finance-v2/projects/{project}/ledger/{entry}'],
            'api.finance-v2.projects.documents.index' => ['GET', 'api/v1/finance-v2/projects/{project}/documents'],
            'api.finance-v2.projects.documents.store' => ['POST', 'api/v1/finance-v2/projects/{project}/documents'],
            'api.finance-v2.projects.documents.destroy' => ['DELETE', 'api/v1/finance-v2/projects/{project}/documents/{link}'],
            'api.finance-v2.projects.document-sources.index' => ['GET', 'api/v1/finance-v2/projects/{project}/document-sources'],
            'api.finance-v2.projects.notes.index' => ['GET', 'api/v1/finance-v2/projects/{project}/notes'],
            'api.finance-v2.projects.notes.store' => ['POST', 'api/v1/finance-v2/projects/{project}/notes'],
            'api.finance-v2.projects.activities.index' => ['GET', 'api/v1/finance-v2/projects/{project}/activities'],
            'api.finance-v2.document-series.notes.index' => ['GET', 'api/v1/finance-v2/document-series/{series}/notes'],
            'api.finance-v2.document-series.notes.store' => ['POST', 'api/v1/finance-v2/document-series/{series}/notes'],
        ];

        foreach ($routes as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('abilities:device', $middleware);
            $this->assertContains('module:finance', $middleware);
            $this->assertContains('throttle:120,1', $middleware);
        }

        foreach (['project', 'workItem', 'entry', 'series'] as $parameter) {
            $matching = array_filter($routes, static fn (array $definition): bool => str_contains($definition[1], "{{$parameter}}"));
            foreach (array_keys($matching) as $name) {
                $this->assertSame('[\\da-fA-F]{8}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{12}', Route::getRoutes()->getByName($name)?->wheres[$parameter] ?? null, $name);
            }
        }

        $this->assertNull(Route::getRoutes()->getByName('api.finance-v2.projects.notes.update'));
        $this->assertNull(Route::getRoutes()->getByName('api.finance-v2.projects.notes.destroy'));
        $this->assertNull(Route::getRoutes()->getByName('api.finance-v2.projects.activities.update'));
        $this->assertNull(Route::getRoutes()->getByName('api.finance-v2.projects.activities.destroy'));

        $this->getJson('/api/v1/finance-v2/projects')->assertUnauthorized();

        $disabled = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $token = $disabled->createToken('device', ['device'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/finance-v2/projects')->assertForbidden();
    }

    public function test_project_create_list_and_detail_use_exact_strings_and_stable_pagination(): void
    {
        [$owner, $token] = $this->ownerAndToken();

        $this->withToken($token)->postJson('/api/v1/finance-v2/projects', [
            ...$this->projectPayload(),
            'budget_minor' => 12550,
        ])->assertUnprocessable()->assertJsonValidationErrors(['budget_minor']);

        $created = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload())
            ->assertCreated()
            ->assertHeader('ETag', '"0"')
            ->assertJsonPath('name', 'Client launch')
            ->assertJsonPath('budget_minor', '12550')
            ->assertJsonPath('version', 0)
            ->assertJsonMissingPath('owner_id')
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('source_id');

        $id = $created->json('id');
        $this->assertIsString($id);

        $page = $this->withToken($token)->getJson('/api/v1/finance-v2/projects?q=Client&kind=business&per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
        $this->assertStringContainsString('q=Client', (string) $page->json('links.first'));
        $this->assertStringContainsString('kind=business', (string) $page->json('links.first'));

        $this->withToken($token)->getJson('/api/v1/finance-v2/projects/'.$id)
            ->assertOk()
            ->assertHeader('ETag', '"0"')
            ->assertJsonPath('id', $id)
            ->assertJsonPath('budget_minor', '12550');

        $this->assertSame(1, DB::table('finance_project_records')->where('user_id', $owner->id)->count());
    }

    public function test_project_detail_is_owner_scoped_and_invalid_filters_are_validation_errors(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        [$other, $otherToken] = $this->ownerAndToken();
        $this->assertNotSame($owner->id, $other->id);

        $created = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload())->assertCreated();
        $id = $created->json('id');
        $this->assertSame($owner->id, DB::table('finance_project_records')->where('uuid', $id)->value('user_id'));

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/finance-v2/projects/'.$id)->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/finance-v2/projects?per_page=101&status=unknown&sort=secret')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'status', 'sort']);
    }

    public function test_project_version_conflicts_and_invalid_transitions_have_stable_codes(): void
    {
        [, $token] = $this->ownerAndToken();
        $id = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload())
            ->assertCreated()->json('id');

        $this->withToken($token)->putJson('/api/v1/finance-v2/projects/'.$id, [
            ...$this->projectPayload('Updated'),
            'version' => 99,
        ])->assertConflict()
            ->assertJsonPath('error', 'version_conflict')
            ->assertJsonPath('current.version', 0)
            ->assertHeader('ETag', '"0"');

        $this->withToken($token)->postJson('/api/v1/finance-v2/projects/'.$id.'/status', [
            'status' => 'done',
            'version' => 0,
        ])->assertUnprocessable()->assertExactJson(['error' => 'invalid_transition']);
    }

    public function test_work_time_ledger_and_totals_use_project_scoped_commands_and_exact_strings(): void
    {
        [, $token] = $this->ownerAndToken();
        $project = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload())
            ->assertCreated()->json('id');

        $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$project}/work-items", [
            'title' => 'Build API',
            'status' => 'open',
            'estimate_hours' => 2.5,
        ])->assertUnprocessable()->assertJsonValidationErrors(['estimate_hours']);

        $work = $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$project}/work-items", [
            'title' => 'Build API',
            'description' => 'Thin boundary',
            'status' => 'open',
            'starts_on' => '2026-09-01',
            'due_on' => '2026-09-02',
            'estimate_hours' => '2.5000',
            'is_milestone' => false,
            'product_reference' => null,
        ])->assertCreated()
            ->assertJsonPath('resource_type', 'work_item')
            ->assertJsonPath('estimate_quantity_scaled', '25000')
            ->assertJsonPath('version', 0)
            ->assertJsonMissingPath('project_owner_id');
        $workId = $work->json('id');

        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/work-items?per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workId)
            ->assertJsonPath('meta.total', 1);

        $time = $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$project}/time-entries", [
            'work_item_id' => $workId,
            'worked_on' => '2026-09-01',
            'hours' => '1.5000',
            'description' => 'Implementation',
            'billable' => true,
            'hourly_rate_minor' => '10000',
            'currency' => 'EUR',
        ])->assertCreated()
            ->assertJsonPath('resource_type', 'time_entry')
            ->assertJsonPath('quantity_scaled', '15000')
            ->assertJsonPath('hourly_rate_minor', '10000');
        $timeId = $time->json('id');

        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/time-entries")
            ->assertOk()->assertJsonPath('data.0.id', $timeId);

        $ledger = $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$project}/ledger", [
            'direction' => 'out',
            'amount_minor' => '500',
            'currency' => 'EUR',
            'occurred_on' => '2026-09-01',
            'title' => 'Hosting',
            'note' => null,
            'category_reference' => null,
            'payment_method_reference' => null,
        ])->assertCreated()
            ->assertJsonPath('resource_type', 'ledger_entry')
            ->assertJsonPath('amount_minor', '500');
        $ledgerId = $ledger->json('id');

        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/ledger?direction=out")
            ->assertOk()->assertJsonPath('data.0.id', $ledgerId);

        $totals = $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/totals")
            ->assertOk()->assertJsonPath('currencies.EUR.hours_scaled', '15000');
        $this->assertIsString($totals->json('currencies.EUR.time_value_minor'));
        $this->assertIsString($totals->json('currencies.EUR.ledger_minor'));
        $this->assertIsString($totals->json('currencies.EUR.financial_minor'));
    }

    public function test_document_source_attach_detach_and_filters_are_owner_safe_idempotent_and_sanitized(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        [, $otherToken] = $this->ownerAndToken();
        $project = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload())
            ->assertCreated()->json('id');
        $fileId = (int) DB::table('files')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Contract.pdf',
            'mime' => 'application/pdf',
            'size' => 123,
            'storage_path' => 'private/do-not-leak.pdf',
            'sha256' => str_repeat('a', 64),
            'favorite' => false,
            'version' => 0,
            'created_at' => '2026-08-29 08:00:00',
            'updated_at' => '2026-08-29 08:00:00',
        ]);

        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/document-sources?source_types[]=file&mime_groups[]=pdf&per_page=1")
            ->assertOk()
            ->assertJsonPath('data.0.source_type', 'file')
            ->assertJsonPath('data.0.source_reference', "file:{$fileId}")
            ->assertJsonPath('data.0.title', 'Contract.pdf')
            ->assertJsonStructure(['data', 'next_cursor'])
            ->assertJsonMissingPath('data.0.storage_path')
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('data.0.ocr');

        $payload = ['source_type' => 'file', 'source_reference' => "file:{$fileId}", 'pinned_revision_id' => null, 'role' => 'file'];
        $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$project}/documents", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);

        $attached = $this->withToken($token)->withHeader('Idempotency-Key', 'attach-http-1')
            ->postJson("/api/v1/finance-v2/projects/{$project}/documents", $payload)
            ->assertCreated()
            ->assertJsonPath('source.source_type', 'file')
            ->assertJsonPath('source.source_reference', "file:{$fileId}")
            ->assertJsonPath('availability', 'available')
            ->assertJsonMissingPath('attached_by')
            ->assertJsonMissingPath('snapshot.storage_path');
        $link = $attached->json('link_id');
        $this->assertIsInt($link);

        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/documents?state=active&roles[]=file")
            ->assertOk()->assertJsonPath('data.0.link_id', $link)->assertJsonPath('meta.total', 1);

        $this->withToken($token)->withoutHeader('Idempotency-Key')->deleteJson("/api/v1/finance-v2/projects/{$project}/documents/{$link}")
            ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);
        $this->withToken($token)->withHeader('Idempotency-Key', 'detach-http-1')
            ->deleteJson("/api/v1/finance-v2/projects/{$project}/documents/{$link}")
            ->assertOk()->assertJsonPath('detached', true);
        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/documents?state=active")
            ->assertOk()->assertJsonPath('meta.total', 0);

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson("/api/v1/finance-v2/projects/{$project}/document-sources")
            ->assertNotFound();
    }

    public function test_notes_and_activity_are_append_only_filterable_cursor_paged_and_sanitized(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        [, $otherToken] = $this->ownerAndToken();
        $project = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload())
            ->assertCreated()->json('id');

        $note = $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$project}/notes", [
            'type' => 'decision',
            'visibility' => 'internal',
            'body' => 'Use the thin controller boundary.',
            'supersedes_note_id' => null,
        ])->assertCreated()
            ->assertJsonPath('source_kind', 'project_note')
            ->assertJsonPath('type', 'decision')
            ->assertJsonPath('body', 'Use the thin controller boundary.')
            ->assertJsonMissingPath('owner_id');
        $this->assertIsInt($note->json('id'));

        $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/notes?types[]=decision&visibilities[]=internal&per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1);

        $activity = $this->withToken($token)->getJson("/api/v1/finance-v2/projects/{$project}/activities?per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'next_cursor']);
        $this->assertIsString($activity->json('next_cursor'));
        $encoded = json_encode($activity->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('raw_error', $encoded);
        $this->assertStringNotContainsString('storage_path', $encoded);

        $series = strtolower((string) Str::uuid());
        DB::table('finance_document_series')->insert([
            'user_id' => $owner->id,
            'uuid' => $series,
            'document_type' => 'quote',
            'status' => 'draft',
            'source_type' => null,
            'source_id' => null,
            'created_by' => $owner->id,
            'created_at' => '2026-08-29 08:00:00',
            'updated_at' => '2026-08-29 08:00:00',
        ]);
        $this->withToken($token)->postJson("/api/v1/finance-v2/document-series/{$series}/notes", [
            'revision_id' => null,
            'type' => 'note',
            'visibility' => 'customer',
            'body' => 'Customer-visible context.',
            'supersedes_note_id' => null,
        ])->assertCreated()->assertJsonPath('series_id', $series)->assertJsonPath('body', 'Customer-visible context.');
        $this->withToken($token)->getJson("/api/v1/finance-v2/document-series/{$series}/notes?visibilities[]=customer")
            ->assertOk()->assertJsonPath('data.0.series_id', $series);

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson("/api/v1/finance-v2/document-series/{$series}/notes")
            ->assertNotFound();
    }

    public function test_move_archive_restore_and_invoice_draft_actions_honor_versions_and_idempotency(): void
    {
        [, $token] = $this->ownerAndToken();
        $parent = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', $this->projectPayload('Parent'))
            ->assertCreated()->json('id');
        $child = $this->withToken($token)->postJson('/api/v1/finance-v2/projects', [
            ...$this->projectPayload('Child'), 'parent_id' => $parent,
        ])->assertCreated()->assertJsonPath('parent_id', $parent)->json('id');

        $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$child}/move", ['parent_id' => null, 'version' => 0])
            ->assertOk()->assertJsonPath('parent_id', null)->assertJsonPath('version', 1);
        $this->withToken($token)->deleteJson("/api/v1/finance-v2/projects/{$child}", ['version' => 1])
            ->assertOk()->assertJsonPath('archived', true)->assertJsonPath('version', 2);
        $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$child}/restore", ['version' => 2])
            ->assertOk()->assertJsonPath('archived', false)->assertJsonPath('version', 3);

        $time = $this->withToken($token)->postJson("/api/v1/finance-v2/projects/{$child}/time-entries", [
            'work_item_id' => null, 'worked_on' => '2026-09-01', 'hours' => '1.0000', 'description' => null,
            'billable' => true, 'hourly_rate_minor' => '10000', 'currency' => 'EUR',
        ])->assertCreated()->json('id');

        $this->withToken($token)->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/finance-v2/projects/{$child}/invoice-drafts", ['time_entry_ids' => [$time]])
            ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);

        $adapter = new ApiProjectToInvoicePort;
        $this->app->instance(ProjectToInvoicePort::class, $adapter);
        $this->withToken($token)->withHeader('Idempotency-Key', 'invoice-http-1')
            ->postJson("/api/v1/finance-v2/projects/{$child}/invoice-drafts", ['time_entry_ids' => [$time]])
            ->assertCreated()
            ->assertJsonPath('target_reference', 'invoice:42')
            ->assertJsonPath('source.source_type', 'legacy_invoice')
            ->assertJsonPath('navigation_url', '/api/v1/finance-v2/invoices/fixture');
        $this->assertSame(1, $adapter->calls);
    }

    public function test_openapi_documents_every_project_path_and_exact_safe_schema(): void
    {
        $openapi = (string) file_get_contents(base_path('../openapi.yaml'));
        foreach ([
            '/finance-v2/projects:',
            '/finance-v2/projects/{project}:',
            '/finance-v2/projects/{project}/status:',
            '/finance-v2/projects/{project}/move:',
            '/finance-v2/projects/{project}/restore:',
            '/finance-v2/projects/{project}/work-items:',
            '/finance-v2/projects/{project}/work-items/{workItem}:',
            '/finance-v2/projects/{project}/work-items/reorder:',
            '/finance-v2/projects/{project}/time-entries:',
            '/finance-v2/projects/{project}/time-entries/{entry}:',
            '/finance-v2/projects/{project}/invoice-drafts:',
            '/finance-v2/projects/{project}/totals:',
            '/finance-v2/projects/{project}/ledger:',
            '/finance-v2/projects/{project}/ledger/{entry}:',
            '/finance-v2/projects/{project}/documents:',
            '/finance-v2/projects/{project}/documents/{link}:',
            '/finance-v2/projects/{project}/document-sources:',
            '/finance-v2/projects/{project}/notes:',
            '/finance-v2/projects/{project}/activities:',
            '/finance-v2/document-series/{series}/notes:',
        ] as $path) {
            $this->assertStringContainsString($path, $openapi);
        }
        foreach ([
            'FinanceV2Project:', 'FinanceV2ProjectPage:', 'FinanceV2WorkItem:', 'FinanceV2TimeEntry:',
            'FinanceV2LedgerEntry:', 'FinanceV2ProjectTotals:', 'FinanceV2ProjectDocument:', 'FinanceV2HistoryItem:',
            'FinanceV2ProjectInput:', 'FinanceV2ProjectActionInput:', 'FinanceV2ProjectDocumentInput:', 'FinanceV2ProjectNoteInput:',
        ] as $schema) {
            $this->assertStringContainsString($schema, $openapi);
        }
        $this->assertStringContainsString("budget_minor: { type: string, nullable: true, pattern: '^-?\\d+$' }", $openapi);
        $this->assertStringContainsString('Idempotency-Key', $openapi);
        $this->assertStringContainsString('Authorized API capability URL; storage metadata paths are never returned.', $openapi);
        $this->assertStringNotContainsString('FinanceV2ProjectStoragePath', $openapi);
        $this->assertStringNotContainsString('FinanceV2ProjectOcrPayload', $openapi);
    }

    /** @return array{User, string} */
    private function ownerAndToken(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        return [$owner, $owner->createToken('device', ['device'])->plainTextToken];
    }

    /** @return array<string, mixed> */
    private function projectPayload(string $name = 'Client launch'): array
    {
        return [
            'name' => $name,
            'kind' => 'business',
            'budget_minor' => '12550',
            'currency' => 'EUR',
            'partner_reference' => null,
            'parent_id' => null,
            'starts_on' => '2026-09-01',
            'due_on' => '2026-09-30',
        ];
    }
}

final class ApiProjectToInvoicePort implements ProjectToInvoicePort
{
    public int $calls = 0;

    public function createDraft(int $ownerId, ProjectView $project, array $lines, array $timeEntryUuids, string $idempotencyKey): InvoiceDraftTarget
    {
        $this->calls++;

        return new InvoiceDraftTarget('invoice:42', new ProjectDocumentSourceRef('legacy_invoice', 'legacy-invoice:42'), '/api/v1/finance-v2/invoices/fixture');
    }
}
