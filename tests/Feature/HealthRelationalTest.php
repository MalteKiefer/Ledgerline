<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HealthEntry;
use App\Models\HealthFast;
use App\Models\HealthProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plaintext-relational Health (pivot): profile upsert, entry CRUD + optimistic
 * version, plaintext-at-rest of the Art. 9 columns, the DB-enforced single-active-
 * fast invariant, stop, and per-user isolation.
 */
class HealthRelationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_upsert_and_read(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson(route('health.profile.save'), [
            'birthdate' => '1990-05-01',
            'height_cm' => 180,
            'sex' => 'male',
            'weight_goal_kg' => '78.5',
        ])->assertOk()->assertJsonPath('profile.height_cm', 180)->assertJsonPath('profile.birthdate', '1990-05-01');

        // Second save updates the same row (one profile per user).
        $this->putJson(route('health.profile.save'), ['height_cm' => 182])
            ->assertOk()->assertJsonPath('profile.height_cm', 182);

        $this->assertSame(1, HealthProfile::query()->count());
        $this->getJson(route('health.data'))->assertOk()->assertJsonPath('profile.height_cm', 182);
    }

    public function test_sensitive_columns_stored_plaintext_at_rest(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson(route('health.profile.save'), ['birthdate' => '1990-05-01', 'weight_goal_kg' => '78.5'])->assertOk();
        $this->postJson(route('health.entries.store'), ['metric' => 'weight', 'ts' => '2026-01-01T08:00:00Z', 'v' => '81.2', 'note' => 'morning'])->assertCreated();

        $profileRaw = DB::table('health_profiles')->first();
        $this->assertNotNull($profileRaw);
        $this->assertStringContainsString('1990-05-01', (string) $profileRaw->birthdate);
        $this->assertStringContainsString('78.5', (string) $profileRaw->weight_goal_kg);

        $entryRaw = DB::table('health_entries')->first();
        $this->assertNotNull($entryRaw);
        $this->assertStringContainsString('81.2', (string) $entryRaw->v);
        $this->assertStringContainsString('morning', (string) $entryRaw->note);
        // Non-sensitive metadata stays plaintext for querying.
        $this->assertSame('weight', $entryRaw->metric);

        // Round-trips as plaintext (encryption removed in v1.516.0).
        $this->assertSame('81.2', HealthEntry::query()->first()?->v);
    }

    public function test_entry_crud_metric_filter_and_optimistic_conflict(): void
    {
        $this->actingAs(User::factory()->create());

        $weightId = $this->postJson(route('health.entries.store'), ['metric' => 'weight', 'ts' => '2026-01-01T08:00:00Z', 'v' => '80'])
            ->assertCreated()->json('entry.id');
        $this->postJson(route('health.entries.store'), ['metric' => 'bp', 'ts' => '2026-01-02T08:00:00Z', 'v' => '120', 'v2' => '80'])->assertCreated();

        $this->getJson(route('health.entries'))->assertOk()->assertJsonCount(2, 'entries');
        $this->getJson(route('health.entries', ['metric' => 'weight']))->assertOk()->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.metric', 'weight');

        // Bad metric rejected by validation (not persisted).
        $this->postJson(route('health.entries.store'), ['metric' => 'bogus', 'ts' => '2026-01-03T08:00:00Z', 'v' => '1']);
        $this->assertSame(2, HealthEntry::query()->count());

        // Optimistic version: matching bumps, stale → 409.
        $this->putJson(route('health.entries.update', $weightId), ['metric' => 'weight', 'ts' => '2026-01-01T08:00:00Z', 'v' => '79', 'version' => 0])
            ->assertOk()->assertJsonPath('entry.v', '79')->assertJsonPath('entry.version', 1);
        $this->putJson(route('health.entries.update', $weightId), ['metric' => 'weight', 'ts' => '2026-01-01T08:00:00Z', 'v' => '77', 'version' => 0])
            ->assertStatus(409);
        $this->assertSame('79', HealthEntry::find($weightId)->v);

        $this->deleteJson(route('health.entries.destroy', $weightId))->assertOk();
        $this->assertSame(1, HealthEntry::query()->count());
    }

    public function test_single_active_fast_is_db_enforced(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('health.fasts.start'), ['target_hours' => 16])->assertCreated();
        // A second start while one is active is rejected by the partial unique index.
        $this->postJson(route('health.fasts.start'), ['target_hours' => 18])
            ->assertStatus(409)->assertJsonPath('error', 'active_fast_exists');

        $this->assertSame(1, HealthFast::query()->whereNull('end_at')->count());
        $this->getJson(route('health.fasts.active'))->assertOk()->assertJsonPath('fast.target_hours', 16);
    }

    public function test_stop_fast_then_start_next(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('health.fasts.start'), ['target_hours' => 16])->assertCreated()->json('fast.id');
        $this->postJson(route('health.fasts.stop', $id))->assertOk();
        $this->assertNotNull(HealthFast::find($id)->end_at);
        $this->assertSame(0, HealthFast::query()->whereNull('end_at')->count());

        // With none active, a new fast starts fine.
        $this->postJson(route('health.fasts.start'), ['target_hours' => 18])->assertCreated();
        $this->assertSame(1, HealthFast::query()->whereNull('end_at')->count());
    }

    public function test_health_data_is_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->actingAs($a)->postJson(route('health.entries.store'), ['metric' => 'weight', 'ts' => '2026-01-01T08:00:00Z', 'v' => '80'])->json('entry.id');

        $this->actingAs($b)->getJson(route('health.entries'))->assertOk()->assertJsonCount(0, 'entries');
        // b's request is owner-scoped → the row is invisible → 404.
        $this->actingAs($b)->deleteJson(route('health.entries.destroy', $id))->assertNotFound();
    }
}
