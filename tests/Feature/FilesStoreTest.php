<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FilesStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilesStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_user_gets_an_empty_files_store(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('files.store.show'))
            ->assertOk()
            ->assertExactJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_saving_advances_the_version_and_persists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('files.store.save'), ['ciphertext' => 'sealed-root', 'version' => 0])
            ->assertOk()
            ->assertJson(['version' => 1]);

        $this->assertSame('sealed-root', FilesStore::query()->where('user_id', $user->id)->value('ciphertext'));
        $this->assertSame(1, (int) FilesStore::query()->where('user_id', $user->id)->value('version'));

        // A subsequent read reflects the persisted ciphertext + bumped version.
        $this->actingAs($user)->getJson(route('files.store.show'))
            ->assertOk()
            ->assertJson(['ciphertext' => 'sealed-root', 'version' => 1]);
    }

    public function test_a_stale_version_is_rejected_with_conflict(): void
    {
        $user = User::factory()->create();

        // First write moves the server to version 1.
        $this->actingAs($user)->putJson(route('files.store.save'), ['ciphertext' => 'x', 'version' => 0])
            ->assertOk();

        // A second write still based on version 0 is a lost-update conflict.
        $this->actingAs($user)->putJson(route('files.store.save'), ['ciphertext' => 'y', 'version' => 0])
            ->assertStatus(409)
            ->assertJson(['error' => 'version_conflict']);

        // The stale write did not overwrite the stored manifest.
        $this->assertSame('x', FilesStore::query()->where('user_id', $user->id)->value('ciphertext'));
    }

    public function test_the_files_store_is_private_to_its_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->putJson(route('files.store.save'), ['ciphertext' => 'alice-root', 'version' => 0])
            ->assertOk();

        // Bob sees his own empty store, never Alice's sealed root.
        $this->actingAs($bob)->getJson(route('files.store.show'))
            ->assertOk()
            ->assertExactJson(['ciphertext' => null, 'version' => 0]);

        $this->assertSame($alice->id, FilesStore::query()->where('ciphertext', 'alice-root')->value('user_id'));
    }

    public function test_a_matching_etag_returns_304_and_a_write_invalidates_it(): void
    {
        $user = User::factory()->create();

        // Empty store still emits a version-derived ETag.
        $first = $this->actingAs($user)->getJson(route('files.store.show'))->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        // Re-GET with the matching If-None-Match → bodyless 304.
        $revalidated = $this->actingAs($user)->getJson(route('files.store.show'), ['If-None-Match' => $etag]);
        $revalidated->assertStatus(304);
        $this->assertSame('', $revalidated->getContent());

        // A write bumps the version, so the ETag must change.
        $this->actingAs($user)->putJson(route('files.store.save'), ['ciphertext' => 'sealed', 'version' => 0])
            ->assertOk();

        $afterWrite = $this->actingAs($user)->getJson(route('files.store.show'))->assertOk();
        $newEtag = $afterWrite->headers->get('ETag');
        $this->assertNotSame($etag, $newEtag);

        // The stale ETag no longer matches → fresh 200 with the current ciphertext.
        $this->actingAs($user)->getJson(route('files.store.show'), ['If-None-Match' => $etag])
            ->assertOk()
            ->assertJson(['ciphertext' => 'sealed', 'version' => 1]);
    }
}
