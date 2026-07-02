<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_dashboard_with_summary_cards(): void
    {
        $this->signIn();
        Photo::factory()->count(2)->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertViewHas('gallery', fn (array $gallery): bool => $gallery['total'] === 2);
    }

    public function test_dashboard_reports_the_vault_state_without_file_details(): void
    {
        $this->signIn();

        // No vault yet.
        $this->get(route('dashboard'))->assertOk()->assertViewHas('vaultConfigured', false);

        Vault::create([
            'salt' => 'c2FsdA==', 'kdf_ops' => 3, 'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'd3JhcHBlZA==', 'wrap_nonce' => 'bm9uY2U=',
            'wrapped_vault_key_recovery' => 'cg==', 'recovery_nonce' => 'cm4=',
        ]);

        $this->get(route('dashboard'))->assertOk()->assertViewHas('vaultConfigured', true);
    }
}
