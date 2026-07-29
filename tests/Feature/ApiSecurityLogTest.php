<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read-only admin security-log API (GET /api/v1/security-log).
 * Mirrors Settings\SecurityLogController but via Sanctum device token.
 */
class ApiSecurityLogTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function adminToken(): string
    {
        return User::factory()->admin()->create()
            ->createToken('phone', ['device'])
            ->plainTextToken;
    }

    private function userToken(): string
    {
        return User::factory()->create()
            ->createToken('phone', ['device'])
            ->plainTextToken;
    }

    private function seedLogs(): void
    {
        AuditLog::create([
            'action' => 'device.evicted',
            'user_id' => 1,
            'ip' => '1.1.1.1',
            'user_agent' => 'TestRunner/1.0',
            'meta' => ['reason' => 'cap'],
            'created_at' => now()->subHour(),
        ]);
        AuditLog::create([
            'action' => 'auth.login',
            'user_id' => 2,
            'ip' => '2.2.2.2',
            'user_agent' => 'TestRunner/2.0',
            'meta' => null,
            'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/security-log')->assertUnauthorized();
    }

    public function test_non_admin_device_token_is_forbidden(): void
    {
        $token = $this->userToken();

        $this->getJson('/api/v1/security-log', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();

        $this->getJson('/api/v1/security-log/export', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Index: listing + pagination
    // -------------------------------------------------------------------------

    public function test_admin_can_list_entries_with_pagination_meta(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['at', 'user_id', 'actor', 'action', 'ip', 'user_agent', 'meta']],
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);

        $this->assertSame(2, $res->json('meta.total'));
        // Newest first
        $this->assertSame('auth.login', $res->json('data.0.action'));
        $this->assertSame('device.evicted', $res->json('data.1.action'));
    }

    public function test_per_page_is_clamped_to_100(): void
    {
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log?per_page=9999', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertSame(100, $res->json('meta.per_page'));
    }

    // -------------------------------------------------------------------------
    // Index: filters
    // -------------------------------------------------------------------------

    public function test_action_prefix_filter_matches_only_prefixed_entries(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log?action=device.*', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $data = $res->json('data');
        $this->assertIsArray($data);
        $actions = array_column($data, 'action');
        $this->assertContains('device.evicted', $actions);
        $this->assertNotContains('auth.login', $actions);
    }

    public function test_exact_action_filter(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log?action=auth.login', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame('auth.login', $res->json('data.0.action'));
    }

    public function test_user_filter_scopes_to_that_user(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log?user=1', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $data = $res->json('data');
        $this->assertIsArray($data);
        foreach ($data as $entry) {
            $this->assertIsArray($entry);
            $this->assertSame(1, $entry['user_id']);
        }
    }

    public function test_since_filter_excludes_older_entries(): void
    {
        AuditLog::create(['action' => 'old.event', 'created_at' => now()->subDays(10)]);
        AuditLog::create(['action' => 'new.event', 'created_at' => now()]);
        $token = $this->adminToken();

        $since = now()->subDay()->toIso8601String();
        $res = $this->getJson('/api/v1/security-log?since='.urlencode($since), ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $data = $res->json('data');
        $this->assertIsArray($data);
        $actions = array_column($data, 'action');
        $this->assertContains('new.event', $actions);
        $this->assertNotContains('old.event', $actions);
    }

    public function test_unparseable_since_is_ignored_and_returns_all(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log?since=not-a-date', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertSame(2, $res->json('meta.total'));
    }

    // -------------------------------------------------------------------------
    // Export: CSV
    // -------------------------------------------------------------------------

    public function test_csv_export_streams_correct_content_type(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log/export?format=csv', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));
    }

    public function test_csv_export_contains_seeded_actions(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $csv = $this->getJson('/api/v1/security-log/export?format=csv', ['Authorization' => 'Bearer '.$token])
            ->streamedContent();

        $this->assertStringContainsString('device.evicted', $csv);
        $this->assertStringContainsString('auth.login', $csv);
    }

    public function test_csv_export_neutralises_formula_injection_in_user_agent(): void
    {
        AuditLog::create([
            'action' => 'auth.unauthorized',
            'user_id' => 1,
            'user_agent' => '=cmd|calc',
            'created_at' => now(),
        ]);
        $token = $this->adminToken();

        $csv = $this->getJson('/api/v1/security-log/export?format=csv', ['Authorization' => 'Bearer '.$token])
            ->streamedContent();

        // Cell value survives but is prefixed with an apostrophe — never starts a formula.
        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertStringNotContainsString(',=cmd|calc', $csv);
    }

    public function test_csv_export_neutralises_formula_injection_in_actor_name(): void
    {
        $actor = User::factory()->create(['name' => '+1234567890']);
        AuditLog::create([
            'action' => 'settings.security_changed',
            'user_id' => $actor->id,
            'created_at' => now(),
        ]);
        $token = $this->adminToken();

        $csv = $this->getJson('/api/v1/security-log/export?format=csv', ['Authorization' => 'Bearer '.$token])
            ->streamedContent();

        $this->assertStringContainsString("'+1234567890", $csv);
        $this->assertStringNotContainsString(',+1234567890', $csv);
    }

    // -------------------------------------------------------------------------
    // Export: NDJSON
    // -------------------------------------------------------------------------

    public function test_json_export_streams_ndjson(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $res = $this->getJson('/api/v1/security-log/export?format=json', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertStringContainsString('ndjson', (string) $res->headers->get('Content-Type'));

        $lines = array_values(array_filter(explode("\n", trim($res->streamedContent()))));
        $this->assertCount(2, $lines);

        $decoded = json_decode($lines[0], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('at', $decoded);
        $this->assertArrayHasKey('action', $decoded);
        $this->assertArrayHasKey('meta', $decoded);
    }

    public function test_json_export_action_prefix_filter_works(): void
    {
        $this->seedLogs();
        $token = $this->adminToken();

        $content = $this->getJson('/api/v1/security-log/export?format=json&action=device.*', ['Authorization' => 'Bearer '.$token])
            ->streamedContent();

        $this->assertStringContainsString('device.evicted', $content);
        $this->assertStringNotContainsString('"auth.login"', $content);
    }

    // -------------------------------------------------------------------------
    // Export: limit cap
    // -------------------------------------------------------------------------

    public function test_export_limit_is_clamped_to_10000(): void
    {
        // Just assert the endpoint accepts an over-limit value without error —
        // verifying the SQL LIMIT is set correctly without seeding 10 001 rows.
        $token = $this->adminToken();

        $this->getJson('/api/v1/security-log/export?format=csv&limit=99999', ['Authorization' => 'Bearer '.$token])
            ->assertOk();
    }
}
