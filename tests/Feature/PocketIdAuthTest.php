<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * The optional Pocket-ID (OIDC) sign-in: coexists with first-party auth, is
 * fully gated off when unconfigured, and never grants privilege from claims.
 */
class PocketIdAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Fake the pocketid config so the feature reports as configured. */
    private function configurePocketId(): void
    {
        config([
            'services.pocketid.base_url' => 'https://id.example.com',
            'services.pocketid.client_id' => 'client-id',
            'services.pocketid.client_secret' => 'client-secret',
            'services.pocketid.redirect' => 'https://app.example.com/auth/callback',
        ]);
    }

    public function test_login_button_hidden_when_unconfigured(): void
    {
        // Default test env leaves POCKETID_* unset.
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('/auth/redirect');
    }

    public function test_login_button_shown_when_configured(): void
    {
        $this->configurePocketId();

        $this->get('/login')
            ->assertOk()
            ->assertSee('/auth/redirect');
    }

    public function test_redirect_route_bounces_to_login_when_unconfigured(): void
    {
        $this->get('/auth/redirect')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_callback_bounces_to_login_when_unconfigured(): void
    {
        $this->get('/auth/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_callback_creates_and_logs_in_user_with_role_user(): void
    {
        $this->configurePocketId();

        $oidcUser = SocialiteUser::fake([
            'id' => 'sub-abc-123',
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'email_verified' => true,
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($oidcUser);
        Socialite::shouldReceive('driver')->with('pocketid')->andReturn($provider);

        $this->get('/auth/callback')->assertRedirect('/finance');

        $user = User::query()->where('oidc_sub', 'sub-abc-123')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role, 'role is never derived from OIDC claims');
        $this->assertSame('alice@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_does_not_grant_admin_even_if_claim_present(): void
    {
        $this->configurePocketId();

        $oidcUser = SocialiteUser::fake([
            'id' => 'sub-evil',
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'email_verified' => true,
            // A hostile provider claiming elevated roles/groups must be ignored.
            'role' => 'admin',
            'groups' => ['admin'],
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($oidcUser);
        Socialite::shouldReceive('driver')->with('pocketid')->andReturn($provider);

        $this->get('/auth/callback');

        $user = User::query()->where('oidc_sub', 'sub-evil')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->managesGlobalSettings());
    }

    public function test_callback_binds_existing_account_by_verified_email(): void
    {
        $this->configurePocketId();

        $existing = User::factory()->create(['email' => 'bob@example.com', 'oidc_sub' => null]);

        $oidcUser = SocialiteUser::fake([
            'id' => 'sub-bob',
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'email_verified' => true,
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($oidcUser);
        Socialite::shouldReceive('driver')->with('pocketid')->andReturn($provider);

        $this->get('/auth/callback')->assertRedirect('/finance');

        $this->assertSame('sub-bob', $existing->fresh()->oidc_sub);
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->count(), 'no duplicate account created');
    }

    public function test_callback_rejects_unverified_email_for_new_account(): void
    {
        $this->configurePocketId();

        $oidcUser = SocialiteUser::fake([
            'id' => 'sub-unverified',
            'name' => 'Carol',
            'email' => 'carol@example.com',
            'email_verified' => false,
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($oidcUser);
        Socialite::shouldReceive('driver')->with('pocketid')->andReturn($provider);

        $this->get('/auth/callback')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }
}
