<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ModuleStore;
use App\Models\StoreHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sealed-root history recovery net: every save retains its root, capped to the
 * last N versions per (user, store), fetchable to re-merge a dropped record. Opaque
 * ciphertext only.
 */
class StoreHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function save(User $user, string $ct, int $version): void
    {
        $this->actingAs($user)->putJson(route('module-store.save', 'notes'), ['ciphertext' => $ct, 'version' => $version])->assertOk();
    }

    public function test_each_save_retains_the_previous_root_and_history_lists_them(): void
    {
        $user = User::factory()->create();
        $this->save($user, 'v1', 0);
        $this->save($user, 'v2', 1);
        $this->save($user, 'v3', 2);

        $res = $this->actingAs($user)->getJson(route('module-store.history', 'notes'))->assertOk()->json('versions');
        $this->assertSame([3, 2, 1], array_column($res, 'version')); // newest first, no ciphertext
        $this->assertArrayNotHasKey('ciphertext', $res[0]);
    }

    public function test_a_prior_version_ciphertext_is_recoverable(): void
    {
        $user = User::factory()->create();
        $this->save($user, 'original', 0);
        $this->save($user, 'clobbered', 1);

        $this->actingAs($user)->getJson(route('module-store.history.version', ['module' => 'notes', 'version' => 1]))
            ->assertOk()
            ->assertJson(['version' => 1, 'ciphertext' => 'original']);
    }

    public function test_every_persisted_version_has_an_atomic_history_snapshot(): void
    {
        // The snapshot is written inside the same write transaction as the live root
        // (not post-commit), so a live version can never exist without its recovery
        // snapshot — the fix for the 2026-08-05 large-write-with-no-history incident.
        $user = User::factory()->create();
        $this->save($user, 'sealed-root-bytes', 0);

        $live = ModuleStore::where('user_id', $user->id)->where('module', 'notes')->firstOrFail();
        $snap = StoreHistory::where('user_id', $user->id)->where('module', 'store:notes')->where('version', $live->version)->firstOrFail();
        $this->assertSame($live->ciphertext, $snap->ciphertext);
    }

    public function test_history_is_capped_to_the_retention_depth(): void
    {
        config()->set('store.history_versions', 3);
        $user = User::factory()->create();
        for ($v = 0; $v < 6; $v++) {
            $this->save($user, "v{$v}", $v);
        }

        $versions = StoreHistory::where('user_id', $user->id)->where('module', 'store:notes')->pluck('version')->sort()->values()->all();
        $this->assertCount(3, $versions);
        $this->assertSame([4, 5, 6], $versions); // only the newest 3 kept
    }

    public function test_history_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->save($owner, 'secret', 0);

        $other = User::factory()->create();
        $this->actingAs($other)->getJson(route('module-store.history', 'notes'))->assertOk()->assertJson(['versions' => []]);
        $this->actingAs($other)->getJson(route('module-store.history.version', ['module' => 'notes', 'version' => 1]))->assertStatus(404);
    }

    public function test_history_is_deleted_with_the_user(): void
    {
        $user = User::factory()->create();
        $this->save($user, 'v1', 0);
        $this->assertDatabaseHas('store_history', ['user_id' => $user->id]);

        $user->delete();
        $this->assertDatabaseMissing('store_history', ['user_id' => $user->id]);
    }
}
