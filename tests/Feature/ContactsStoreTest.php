<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactBlob;
use App\Models\ContactsStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sharded contacts store (merge-safety spec §3b) — the sealed root + shard-ref guard,
 * mirroring the invoices/notes sharded stores. Record shards reuse the contact_blobs
 * ledger (shared with avatar blobs), so the guard is driven by ContactBlob rows.
 */
class ContactsStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_store_reads_as_null_version_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('contacts.store.show'))->assertOk()->assertJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_save_then_read_bumps_version(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('contacts.store.save'), ['ciphertext' => 'sealed-a', 'version' => 0])->assertOk()->assertJson(['version' => 1]);
        $this->getJson(route('contacts.store.show'))->assertOk()->assertJson(['ciphertext' => 'sealed-a', 'version' => 1]);
    }

    public function test_stale_version_is_a_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('contacts.store.save'), ['ciphertext' => 'a', 'version' => 0])->assertOk();
        $this->putJson(route('contacts.store.save'), ['ciphertext' => 'b', 'version' => 0])->assertStatus(409);
    }

    public function test_store_is_private_to_its_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->putJson(route('contacts.store.save'), ['ciphertext' => 'alice', 'version' => 0])->assertOk();
        $this->actingAs($bob)->getJson(route('contacts.store.show'))->assertOk()->assertJson(['ciphertext' => null, 'version' => 0]);
        $this->assertSame($alice->id, ContactsStore::query()->where('ciphertext', 'alice')->value('user_id'));
    }

    public function test_save_with_present_shard_refs_is_accepted(): void
    {
        $user = User::factory()->create();
        $blob = (string) Str::uuid();
        ContactBlob::query()->create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10]);

        $this->actingAs($user)
            ->putJson(route('contacts.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$blob]])
            ->assertOk()->assertJson(['version' => 1]);
    }

    public function test_save_referencing_a_missing_shard_is_rejected(): void
    {
        $user = User::factory()->create();
        $ghost = (string) Str::uuid();
        $this->actingAs($user)
            ->putJson(route('contacts.store.save'), ['ciphertext' => 'a', 'version' => 0, 'shards' => [$ghost]])
            ->assertStatus(422)->assertJson(['error' => 'missing_shard']);
        $this->assertNull(ContactsStore::query()->where('user_id', $user->id)->value('ciphertext'));
    }

    public function test_save_without_shard_refs_still_works(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('contacts.store.save'), ['ciphertext' => 'a', 'version' => 0])->assertOk()->assertJson(['version' => 1]);
    }
}
