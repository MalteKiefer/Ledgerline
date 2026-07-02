<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'salt' => 'c2FsdHNhbHQ=',
            'kdf_ops' => 3,
            'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'd3JhcHBlZA==',
            'wrap_nonce' => 'bm9uY2U=',
            'wrapped_vault_key_recovery' => 'cmVjb3Zlcg==',
            'recovery_nonce' => 'cm5vbmNl',
        ], $overrides);
    }

    public function test_guests_cannot_touch_the_vault(): void
    {
        $this->get(route('vault.show'))->assertRedirect(route('login'));
    }

    public function test_show_reports_unconfigured_then_configured(): void
    {
        $this->signIn();
        $this->getJson(route('vault.show'))->assertOk()->assertJson(['configured' => false]);

        Vault::create($this->payload());

        $this->getJson(route('vault.show'))->assertOk()->assertJson([
            'configured' => true,
            'has_recovery' => true,
            'wrapped_vault_key' => 'd3JhcHBlZA==',
        ]);
    }

    public function test_setup_stores_only_ciphertext_and_refuses_overwrite(): void
    {
        $this->signIn();

        $this->postJson(route('vault.store'), $this->payload())->assertCreated();
        $this->assertDatabaseCount('vault', 1);

        // A second setup is refused (that path is a rotate).
        $this->postJson(route('vault.store'), $this->payload())->assertStatus(409);
    }

    public function test_rotate_rewraps_the_vault_key(): void
    {
        $this->signIn();
        Vault::create($this->payload());

        $this->putJson(route('vault.rotate'), $this->payload(['wrapped_vault_key' => 'cm90YXRlZA==']))
            ->assertOk();

        $this->assertSame('cm90YXRlZA==', Vault::current()->wrapped_vault_key);
        $this->assertDatabaseCount('vault', 1);
    }

    public function test_a_passphrase_change_rotate_keeps_the_recovery_wrap(): void
    {
        $this->signIn();
        Vault::create($this->payload());

        // A passphrase change re-wraps only the vault key; no recovery fields.
        $this->putJson(route('vault.rotate'), [
            'salt' => 'bmV3c2FsdA==',
            'kdf_ops' => 3,
            'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'cmV3cmFwcGVk',
            'wrap_nonce' => 'bmV3bm9uY2U=',
        ])->assertOk();

        $vault = Vault::current();
        $this->assertSame('cmV3cmFwcGVk', $vault->wrapped_vault_key);
        $this->assertSame('bmV3c2FsdA==', $vault->salt);
        // The original recovery wrap is untouched.
        $this->assertSame('cmVjb3Zlcg==', $vault->wrapped_vault_key_recovery);
        $this->assertSame('cm5vbmNl', $vault->recovery_nonce);
    }
}
