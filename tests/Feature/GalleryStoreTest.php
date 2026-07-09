<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
