<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NoteBlob;
use App\Models\NotesStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sharded notes store (merge-safety spec §3b) — the sealed root + shard-ref guard,
 * mirroring the gallery/files sharded stores.
 */
class NotesStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_store_reads_as_null_version_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('notes.store.show'))->assertOk()->assertJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_save_then_read_bumps_version(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('notes.store.save'), ['ciphertext' => 'sealed-a', 'version' => 0])->assertOk()->assertJson(['version' => 1]);
        $this->getJson(route('notes.store.show'))->assertOk()->assertJson(['ciphertext' => 'sealed-a', 'version' => 1]);
    }

    public function test_stale_version_is_a_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('notes.store.save'), ['ciphertext' => 'a', 'version' => 0])->assertOk();
        $this->putJson(route('notes.store.save'), ['ciphertext' => 'b', 'version' => 0])->assertStatus(409);
    }

    public function test_store_is_private_to_its_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->putJson(route('notes.store.save'), ['ciphertext' => 'alice', 'version' => 0])->assertOk();
        $this->actingAs($bob)->getJson(route('notes.store.show'))->assertOk()->assertJson(['ciphertext' => null, 'version' => 0]);
        $this->assertSame($alice->id, NotesStore::query()->where('ciphertext', 'alice')->value('user_id'));
    }

    public function test_save_with_present_shard_refs_is_accepted(): void
    {
        $user = User::factory()->create();
        $blob = (string) Str::uuid();
        NoteBlob::query()->create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10]);

        $this->actingAs($user)
            ->putJson(route('notes.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$blob]])
            ->assertOk()->assertJson(['version' => 1]);
    }

    public function test_save_referencing_a_missing_shard_is_rejected(): void
    {
        $user = User::factory()->create();
        $ghost = (string) Str::uuid();
        $this->actingAs($user)
            ->putJson(route('notes.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$ghost]])
            ->assertStatus(422)->assertJson(['error' => 'missing_shard']);
        $this->assertNull(NotesStore::query()->where('user_id', $user->id)->value('ciphertext'));
    }

    public function test_save_without_shard_refs_still_works(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('notes.store.save'), ['ciphertext' => 'a', 'version' => 0])->assertOk()->assertJson(['version' => 1]);
    }
}
