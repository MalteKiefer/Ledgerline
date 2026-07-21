<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ModuleStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_user_gets_an_empty_module_store(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('module-store.show', 'notes'))
            ->assertOk()
            ->assertExactJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_an_unknown_module_is_a_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('module-store.show', 'bogus'))
            ->assertNotFound();
    }

    public function test_saving_advances_the_version_and_persists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('module-store.save', 'notes'), ['ciphertext' => 'x', 'version' => 0])
            ->assertOk()
            ->assertJson(['version' => 1]);

        $this->assertSame('x', ModuleStore::query()->where('user_id', $user->id)->where('module', 'notes')->value('ciphertext'));
        $this->assertSame(1, (int) ModuleStore::query()->where('user_id', $user->id)->where('module', 'notes')->value('version'));
    }

    public function test_modules_are_isolated_from_each_other(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('module-store.save', 'notes'), ['ciphertext' => 'n', 'version' => 0])
            ->assertOk();

        // A different module is still empty — the notes write did not touch it.
        $this->actingAs($user)->getJson(route('module-store.show', 'todos'))
            ->assertOk()
            ->assertExactJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_a_stale_version_is_rejected_with_conflict(): void
    {
        $user = User::factory()->create();

        // First write moves the server to version 1.
        $this->actingAs($user)->putJson(route('module-store.save', 'notes'), ['ciphertext' => 'x', 'version' => 0])
            ->assertOk();

        // A second write still based on version 0 is a lost-update conflict.
        $this->actingAs($user)->putJson(route('module-store.save', 'notes'), ['ciphertext' => 'y', 'version' => 0])
            ->assertStatus(409)
            ->assertJson(['error' => 'version_conflict']);

        // The stale write did not overwrite the stored manifest.
        $this->assertSame('x', ModuleStore::query()->where('user_id', $user->id)->where('module', 'notes')->value('ciphertext'));
    }

    public function test_a_matching_etag_returns_304_and_a_write_invalidates_it(): void
    {
        $user = User::factory()->create();

        // Even an empty module store emits a version-derived ETag.
        $first = $this->actingAs($user)->getJson(route('module-store.show', 'notes'))->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        // Re-GET with the matching If-None-Match → bodyless 304.
        $revalidated = $this->actingAs($user)->getJson(route('module-store.show', 'notes'), ['If-None-Match' => $etag]);
        $revalidated->assertStatus(304);
        $this->assertSame('', $revalidated->getContent());

        // A write bumps the version, so the ETag must change.
        $this->actingAs($user)->putJson(route('module-store.save', 'notes'), ['ciphertext' => 'x', 'version' => 0])
            ->assertOk();

        $afterWrite = $this->actingAs($user)->getJson(route('module-store.show', 'notes'))->assertOk();
        $newEtag = $afterWrite->headers->get('ETag');
        $this->assertNotSame($etag, $newEtag);

        // The stale ETag no longer matches → fresh 200 with the current ciphertext.
        $this->actingAs($user)->getJson(route('module-store.show', 'notes'), ['If-None-Match' => $etag])
            ->assertOk()
            ->assertJson(['ciphertext' => 'x', 'version' => 1]);
    }
}
