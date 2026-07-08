<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function payload(bool $recovery = true): array
    {
        return [
            'salt' => 'c2FsdA==',
            'kdf_ops' => 3,
            'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'd3JhcHBlZA==',
            'wrap_nonce' => 'bm9uY2U=',
            'wrapped_vault_key_recovery' => $recovery ? 'cmVjb3Zlcnk=' : null,
            'recovery_nonce' => $recovery ? 'cm5vbmNl' : null,
        ];
    }

    public function test_show_reports_unconfigured_then_configured(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->getJson(route('vault.show'))
            ->assertOk()->assertJson(['configured' => false]);

        $this->actingAs($u)->postJson(route('vault.store'), $this->payload())
            ->assertCreated()->assertJson(['configured' => true]);

        $this->actingAs($u)->getJson(route('vault.show'))
            ->assertOk()->assertJson(['configured' => true, 'has_recovery' => true])
            ->assertJsonPath('wrapped_vault_key', 'd3JhcHBlZA==');
    }

    public function test_store_refuses_to_overwrite_existing_vault(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson(route('vault.store'), $this->payload())->assertCreated();

        $this->actingAs($u)->postJson(route('vault.store'), $this->payload())->assertStatus(409);
    }

    public function test_rotate_rewraps_without_new_vault_row(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson(route('vault.store'), $this->payload())->assertCreated();

        $this->actingAs($u)->putJson(route('vault.rotate'), [
            'salt' => 'bmV3c2FsdA==', 'kdf_ops' => 3, 'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'bmV3d3JhcA==', 'wrap_nonce' => 'bm5vbmNl',
        ])->assertOk()->assertJson(['configured' => true]);

        $this->assertSame(1, Vault::query()->where('user_id', $u->id)->count());
        $this->assertSame('bmV3d3JhcA==', Vault::query()->where('user_id', $u->id)->first()->wrapped_vault_key);
    }

    public function test_rotate_without_a_vault_is_404(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson(route('vault.rotate'), $this->payload(false))->assertStatus(404);
    }

    public function test_a_vault_is_per_user_isolated(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->postJson(route('vault.store'), $this->payload())->assertCreated();

        // B has no vault of their own and never sees A's ciphertext.
        $this->actingAs($b)->getJson(route('vault.show'))->assertOk()->assertJson(['configured' => false]);
        // B can set up their own independently.
        $this->actingAs($b)->postJson(route('vault.store'), $this->payload())->assertCreated();
        $this->assertSame(2, Vault::query()->count());
    }
}
