<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryBlob;
use App\Models\GalleryStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('gallery.store.show'))->assertRedirect(route('login'));
    }

    public function test_empty_store_reads_as_null_version_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('gallery.store.show'))->assertOk()
            ->assertJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_save_then_read_bumps_version(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'sealed-a', 'version' => 0])
            ->assertOk()->assertJson(['version' => 1]);
        $this->getJson(route('gallery.store.show'))->assertOk()
            ->assertJson(['ciphertext' => 'sealed-a', 'version' => 1]);
    }

    public function test_stale_version_is_a_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'a', 'version' => 0])->assertOk();
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'b', 'version' => 0])
            ->assertStatus(409);
    }

    public function test_store_is_private_to_its_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->putJson(route('gallery.store.save'), ['ciphertext' => 'alice', 'version' => 0])->assertOk();
        $this->actingAs($bob)->getJson(route('gallery.store.show'))->assertOk()
            ->assertJson(['ciphertext' => null, 'version' => 0]);
        $this->assertSame($alice->id, GalleryStore::query()->where('ciphertext', 'alice')->value('user_id'));
    }

    public function test_save_with_present_shard_refs_is_accepted(): void
    {
        $user = User::factory()->create();
        $blob = (string) Str::uuid();
        GalleryBlob::query()->create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10]);

        $this->actingAs($user)
            ->putJson(route('gallery.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$blob]])
            ->assertOk()->assertJson(['version' => 1]);
    }

    public function test_save_referencing_a_missing_shard_is_rejected(): void
    {
        $user = User::factory()->create();
        $ghost = (string) Str::uuid(); // never inserted into the ledger

        $this->actingAs($user)
            ->putJson(route('gallery.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$ghost]])
            ->assertStatus(422)->assertJson(['error' => 'missing_shard']);

        // The dangling root must NOT have been persisted.
        $this->assertDatabaseMissing('gallery_store', ['user_id' => $user->id]);
    }

    public function test_a_shard_owned_by_another_user_does_not_count_as_present(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $blob = (string) Str::uuid();
        GalleryBlob::query()->create(['blob' => $blob, 'user_id' => $bob->id, 'size' => 10]);

        // Alice references Bob's blob — not in her ledger scope → rejected.
        $this->actingAs($alice)
            ->putJson(route('gallery.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$blob]])
            ->assertStatus(422)->assertJson(['error' => 'missing_shard']);
    }

    public function test_save_without_shard_refs_still_works(): void
    {
        // Backward compatible: a client that omits `shards` (e.g. an older mobile
        // build) is not blocked by the guard.
        $this->actingAs(User::factory()->create());
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'a', 'version' => 0])
            ->assertOk()->assertJson(['version' => 1]);
    }
}
