<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_user_gets_an_empty_manifest(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('store.show'))
            ->assertOk()
            ->assertExactJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_saving_advances_the_version_and_persists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('store.save'), ['ciphertext' => 'x', 'version' => 0])
            ->assertOk()
            ->assertJson(['version' => 1]);

        $this->assertSame('x', VaultStore::query()->where('user_id', $user->id)->value('ciphertext'));
        $this->assertSame(1, (int) VaultStore::query()->where('user_id', $user->id)->value('version'));
    }

    public function test_a_stale_version_is_rejected_with_conflict(): void
    {
        $user = User::factory()->create();

        // First write moves the server to version 1.
        $this->actingAs($user)->putJson(route('store.save'), ['ciphertext' => 'x', 'version' => 0])
            ->assertOk();

        // A second write still based on version 0 is a lost-update conflict.
        $this->actingAs($user)->putJson(route('store.save'), ['ciphertext' => 'y', 'version' => 0])
            ->assertStatus(409)
            ->assertJson(['error' => 'version_conflict']);

        // The stale write did not overwrite the stored manifest.
        $this->assertSame('x', VaultStore::query()->where('user_id', $user->id)->value('ciphertext'));
    }
}
