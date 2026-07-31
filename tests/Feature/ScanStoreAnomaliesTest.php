<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * store:anomaly-scan turns the root_write count trail into store.count_regression
 * audit events — the automatic "a record vanished" detector.
 */
class ScanStoreAnomaliesTest extends TestCase
{
    use RefreshDatabase;

    private function write(User $user, string $module, int $version, int $count): void
    {
        $this->actingAs($user)->putJson(route('module-store.save', $module), [
            'ciphertext' => str_repeat('a', $count + 1),
            'version' => $version,
            'counts' => [$module => $count],
        ])->assertOk();
    }

    public function test_it_flags_a_count_regression_between_versions(): void
    {
        $user = User::factory()->create();
        $this->write($user, 'notes', 0, 5); // v1: 5 notes
        $this->write($user, 'notes', 1, 6); // v2: 6 notes (grew — fine)
        $this->write($user, 'notes', 2, 4); // v3: 4 notes (DROPPED 2 — regression)

        $this->artisan('store:anomaly-scan', ['--since' => '24h'])->assertSuccessful();

        $reg = AuditLog::where('action', 'store.count_regression')->get();
        $this->assertCount(1, $reg);
        $this->assertSame('store:notes', $reg[0]->meta['module']);
        $this->assertSame(6, $reg[0]->meta['from_total']);
        $this->assertSame(4, $reg[0]->meta['to_total']);
        $this->assertSame(['notes' => -2], $reg[0]->meta['drops']);
        $this->assertSame($user->id, $reg[0]->user_id);
    }

    public function test_a_pure_growth_history_flags_nothing(): void
    {
        $user = User::factory()->create();
        $this->write($user, 'notes', 0, 1);
        $this->write($user, 'notes', 1, 3);
        $this->write($user, 'notes', 2, 3);

        $this->artisan('store:anomaly-scan')->assertSuccessful();
        $this->assertSame(0, AuditLog::where('action', 'store.count_regression')->count());
    }

    public function test_it_is_idempotent_and_does_not_double_record(): void
    {
        $user = User::factory()->create();
        $this->write($user, 'notes', 0, 5);
        $this->write($user, 'notes', 1, 2);

        $this->artisan('store:anomaly-scan')->assertSuccessful();
        $this->artisan('store:anomaly-scan')->assertSuccessful();

        $this->assertSame(1, AuditLog::where('action', 'store.count_regression')->count());
    }

    public function test_dry_run_reports_without_recording(): void
    {
        $user = User::factory()->create();
        $this->write($user, 'notes', 0, 5);
        $this->write($user, 'notes', 1, 1);

        $this->artisan('store:anomaly-scan', ['--dry' => true])->assertSuccessful();
        $this->assertSame(0, AuditLog::where('action', 'store.count_regression')->count());
    }
}
