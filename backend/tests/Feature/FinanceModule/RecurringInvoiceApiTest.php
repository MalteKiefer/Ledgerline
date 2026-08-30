<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RecurringInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_routes_are_additive_uuid_scoped_and_protected_by_the_finance_stack(): void
    {
        $routes = [
            'api.finance-v2.recurring-invoice-templates.index' => ['GET', 'api/v1/finance-v2/recurring-invoice-templates'],
            'api.finance-v2.recurring-invoice-templates.store' => ['POST', 'api/v1/finance-v2/recurring-invoice-templates'],
            'api.finance-v2.recurring-invoice-templates.show' => ['GET', 'api/v1/finance-v2/recurring-invoice-templates/{template}'],
            'api.finance-v2.recurring-invoice-templates.versions.store' => ['POST', 'api/v1/finance-v2/recurring-invoice-templates/{template}/versions'],
            'api.finance-v2.recurring-invoice-templates.pause' => ['POST', 'api/v1/finance-v2/recurring-invoice-templates/{template}/pause'],
            'api.finance-v2.recurring-invoice-templates.resume' => ['POST', 'api/v1/finance-v2/recurring-invoice-templates/{template}/resume'],
            'api.finance-v2.recurring-invoice-templates.runs.index' => ['GET', 'api/v1/finance-v2/recurring-invoice-templates/{template}/runs'],
            'api.finance-v2.recurring-invoice-runs.retry' => ['POST', 'api/v1/finance-v2/recurring-invoice-runs/{run}/retry'],
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

        $this->getJson('/api/v1/finance-v2/recurring-invoice-templates')->assertUnauthorized();

        $disabled = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $disabledToken = $disabled->createToken('device', ['device'])->plainTextToken;
        $this->withToken($disabledToken)->getJson('/api/v1/finance-v2/recurring-invoice-templates')->assertForbidden();
    }

    public function test_create_requires_idempotency_and_the_initial_version_is_one(): void
    {
        [, $token] = $this->ownerAndToken();

        $this->withToken($token)->postJson('/api/v1/finance-v2/recurring-invoice-templates', $this->templatePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $created = $this->withToken($token)->withHeader('Idempotency-Key', 'template-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates', $this->templatePayload())
            ->assertCreated()
            ->assertJsonPath('mode', 'draft')
            ->assertJsonPath('interval', 'monthly')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('current_version.number', 1)
            ->assertJsonPath('version', 0);
        $this->assertIsString($created->json('id'));

        $replay = $this->withToken($token)->withHeader('Idempotency-Key', 'template-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates', $this->templatePayload())
            ->assertCreated();
        $this->assertSame($created->json('id'), $replay->json('id'));
        $this->assertSame(1, DB::table('finance_recurring_invoice_templates')->count());
    }

    public function test_show_list_and_owner_scope(): void
    {
        [, $token] = $this->ownerAndToken();
        [, $otherToken] = $this->ownerAndToken();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', 'template-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates', $this->templatePayload())
            ->assertCreated();
        $uuid = $created->json('id');

        $this->withToken($token)->getJson('/api/v1/finance-v2/recurring-invoice-templates/'.$uuid)
            ->assertOk()
            ->assertJsonPath('id', $uuid);

        $this->withToken($token)->getJson('/api/v1/finance-v2/recurring-invoice-templates?mode=draft')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/finance-v2/recurring-invoice-templates/'.$uuid)
            ->assertNotFound();
    }

    public function test_add_version_pause_and_resume_use_optimistic_versioning(): void
    {
        [, $token] = $this->ownerAndToken();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', 'template-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates', $this->templatePayload())
            ->assertCreated();
        $uuid = $created->json('id');

        $versionPayload = $this->templatePayload()['draft'];
        $versionPayload['lines'][0]['unit_price'] = '150.00';

        $added = $this->withToken($token)->withHeader('Idempotency-Key', 'version-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates/'.$uuid.'/versions', [
                'effective_from' => '2026-09-28',
                'expected_version' => 0,
                'draft' => $versionPayload,
            ])
            ->assertCreated()
            ->assertJsonPath('current_version.number', 2)
            ->assertJsonPath('version', 1);

        $stale = $this->withToken($token)->withHeader('Idempotency-Key', 'version-stale')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates/'.$uuid.'/versions', [
                'effective_from' => '2026-10-28',
                'expected_version' => 0,
                'draft' => $versionPayload,
            ])
            ->assertConflict()
            ->assertJsonPath('error', 'recurring_template_version_conflict');

        $paused = $this->withToken($token)->withHeader('Idempotency-Key', 'pause-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates/'.$uuid.'/pause', ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'paused');
        $this->assertIsString($paused->json('paused_at'));

        $this->withToken($token)->withHeader('Idempotency-Key', 'resume-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates/'.$uuid.'/resume', ['expected_version' => 2])
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('paused_at', null);
    }

    public function test_runs_index_and_retry_use_the_persisted_safe_step(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', 'template-1')
            ->postJson('/api/v1/finance-v2/recurring-invoice-templates', $this->templatePayload())
            ->assertCreated();
        $templateUuid = $created->json('id');
        $templateId = (int) DB::table('finance_recurring_invoice_templates')->where('uuid', $templateUuid)->value('id');
        $versionId = (int) DB::table('finance_recurring_invoice_template_versions')->where('template_id', $templateId)->value('id');

        $now = now();
        $runUuid = '018f4ca3-224d-7d8d-9f06-1234567890ab';
        DB::table('finance_recurring_invoice_runs')->insert([
            'user_id' => $owner->id,
            'uuid' => $runUuid,
            'template_id' => $templateId,
            'template_version_id' => $versionId,
            'scheduled_for' => $now,
            'scheduled_local_date' => $now->format('Y-m-d'),
            'status' => 'failed',
            'last_completed_step' => null,
            'attempts' => 1,
            'last_error_code' => 'invoice_finalization_conflict',
            'idempotency_key_hash' => hash('sha256', 'seed-run'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withToken($token)->getJson('/api/v1/finance-v2/recurring-invoice-templates/'.$templateUuid.'/runs')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $runUuid)
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonMissingPath('data.0.idempotency_key_hash');

        $this->withToken($token)->getJson('/api/v1/finance-v2/recurring-invoice-templates/'.$templateUuid.'/runs?status=sent')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)
            ->postJson('/api/v1/finance-v2/recurring-invoice-runs/'.$runUuid.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('id', $runUuid);
        $this->assertSame('pending', DB::table('finance_recurring_invoice_runs')->where('uuid', $runUuid)->value('status'));
    }

    /** @return array{User, string} */
    private function ownerAndToken(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        return [$owner, $owner->createToken('device', ['device'])->plainTextToken];
    }

    /** @return array<string, mixed> */
    private function templatePayload(): array
    {
        return [
            'mode' => 'draft',
            'interval' => 'monthly',
            'timezone' => 'Europe/Berlin',
            'start_date' => '2026-08-28',
            'end_date' => null,
            'run_time' => '08:00:00',
            'draft' => [
                'issue_date' => '2026-08-28',
                'due_date' => '2026-09-11',
                'currency' => 'EUR',
                'customer' => ['name' => 'ACME', 'email' => 'billing@example.test'],
                'partner_id' => null,
                'project_id' => null,
                'lines' => [[
                    'description' => 'Hosting',
                    'quantity' => '1.0000',
                    'unit' => 'month',
                    'unit_price' => '100.00',
                    'tax_rate' => '19.00',
                    'kind' => 'service',
                    'product_id' => null,
                ]],
                'discount_type' => 'none',
                'discount_value' => null,
            ],
        ];
    }
}
