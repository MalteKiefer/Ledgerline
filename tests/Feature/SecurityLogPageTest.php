<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin security-log viewer: admin-gated, filterable, exportable.
 */
class SecurityLogPageTest extends TestCase
{
    use RefreshDatabase;

    private function seedLogs(): void
    {
        AuditLog::create(['action' => 'device.evicted', 'user_id' => 1, 'ip' => '1.1.1.1', 'meta' => ['reason' => 'cap'], 'created_at' => now()->subHour()]);
        AuditLog::create(['action' => 'auth.login', 'user_id' => 2, 'ip' => '2.2.2.2', 'meta' => null, 'created_at' => now()]);
    }

    public function test_non_admin_cannot_open_the_security_log(): void
    {
        config(['services.pocketid.admin_group' => 'admins']);
        $this->actingAs(User::factory()->create(['groups' => ['users']]));
        $this->get(route('settings.security-log'))->assertForbidden();
    }

    public function test_admin_sees_entries(): void
    {
        config(['services.pocketid.admin_group' => null]); // everyone admin when unset
        $this->seedLogs();
        $this->actingAs(User::factory()->create())
            ->get(route('settings.security-log'))
            ->assertOk()
            ->assertSee('device.evicted')
            ->assertSee('auth.login');
    }

    public function test_action_prefix_filter(): void
    {
        config(['services.pocketid.admin_group' => null]);
        $this->seedLogs();
        // Filter via the export so we assert on the rows, not the (all-actions)
        // filter dropdown which always lists every distinct action.
        $csv = $this->actingAs(User::factory()->create())
            ->get(route('settings.security-log', ['action' => 'device.*', 'export' => 'csv']))
            ->streamedContent();
        $this->assertStringContainsString('device.evicted', $csv);
        $this->assertStringNotContainsString('auth.login', $csv);
    }

    public function test_csv_export_streams_the_rows(): void
    {
        config(['services.pocketid.admin_group' => null]);
        $this->seedLogs();
        $res = $this->actingAs(User::factory()->create())
            ->get(route('settings.security-log', ['export' => 'csv']));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('device.evicted', $res->streamedContent());
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        config(['services.pocketid.admin_group' => null]);
        // A client-controlled cell (user-agent) starting with a formula char.
        AuditLog::create(['action' => 'auth.unauthorized', 'user_id' => 1, 'user_agent' => '=cmd|calc', 'created_at' => now()]);

        $csv = $this->actingAs(User::factory()->create())
            ->get(route('settings.security-log', ['export' => 'csv']))->streamedContent();

        // The value survives but is neutralised with a leading apostrophe, so the
        // cell never begins a formula.
        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertStringNotContainsString(',=cmd|calc', $csv);
    }
}
