<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_captures_actor_action_and_request_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        AuditLog::record('auth.login', $user, ['public_computer' => false]);

        $entry = AuditLog::firstOrFail();
        $this->assertSame('auth.login', $entry->action);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($user->getMorphClass(), $entry->subject_type);
        $this->assertSame((string) $user->id, $entry->subject_id);
        $this->assertSame(['public_computer' => false], $entry->meta);
        $this->assertNotNull($entry->created_at);
    }

    public function test_entries_are_append_only(): void
    {
        $entry = AuditLog::create(['action' => 'x', 'created_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_record_never_throws_even_without_a_request_user(): void
    {
        // No actingAs — Auth::id() is null; must still record cleanly.
        AuditLog::record('auth.logout');
        $this->assertSame(1, AuditLog::count());
        $this->assertNull(AuditLog::first()->user_id);
    }

    public function test_prune_command_drops_entries_past_retention(): void
    {
        config(['ops.audit_retention_days' => 30]);

        AuditLog::create(['action' => 'old', 'created_at' => Carbon::now()->subDays(40)]);
        AuditLog::create(['action' => 'fresh', 'created_at' => Carbon::now()->subDays(5)]);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('fresh', AuditLog::first()->action);
    }

    public function test_backups_verify_runs_cleanly_with_no_jobs(): void
    {
        $this->artisan('backups:verify')->assertSuccessful();
    }
}
