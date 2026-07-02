<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultManifestTest extends TestCase
{
    use RefreshDatabase;

    private function makeVault(): Vault
    {
        return Vault::create([
            'salt' => 'c2FsdA==', 'kdf_ops' => 3, 'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'd3JhcHBlZA==', 'wrap_nonce' => 'bm9uY2U=',
            'wrapped_vault_key_recovery' => 'cg==', 'recovery_nonce' => 'cm4=',
        ]);
    }

    public function test_guests_cannot_touch_the_manifest(): void
    {
        $this->get(route('vault.manifest.show'))->assertRedirect(route('login'));
    }

    public function test_manifest_404s_without_a_vault(): void
    {
        $this->signIn();

        $this->getJson(route('vault.manifest.show'))->assertNotFound();
    }

    public function test_manifest_roundtrips_as_opaque_ciphertext(): void
    {
        $this->signIn();
        $this->makeVault();

        // Empty at first.
        $this->getJson(route('vault.manifest.show'))
            ->assertOk()
            ->assertJson(['cipher' => null, 'version' => 0]);

        // Store a ciphertext; the server bumps the version and returns it as-is.
        $this->putJson(route('vault.manifest.update'), [
            'cipher' => 'b3BhcXVl', 'nonce' => 'bm9uY2U=', 'version' => 0,
        ])->assertOk()->assertJson(['version' => 1]);

        $this->getJson(route('vault.manifest.show'))
            ->assertOk()
            ->assertJson(['cipher' => 'b3BhcXVl', 'nonce' => 'bm9uY2U=', 'version' => 1]);
    }

    public function test_a_stale_writer_is_rejected_with_the_current_version(): void
    {
        $this->signIn();
        $vault = $this->makeVault();
        $vault->update(['manifest_cipher' => 'YQ==', 'manifest_nonce' => 'bg==', 'manifest_version' => 5]);

        $this->putJson(route('vault.manifest.update'), [
            'cipher' => 'Yg==', 'nonce' => 'bg==', 'version' => 4,
        ])->assertStatus(409)->assertJson(['version' => 5]);

        // Nothing changed.
        $this->assertSame('YQ==', $vault->fresh()->manifest_cipher);
    }
}
