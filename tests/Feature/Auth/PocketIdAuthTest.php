<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class PocketIdAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a fake Socialite user and bind it to the pocketid driver.
     *
     * @param  array<string, mixed>  $raw
     */
    private function fakeSocialiteUser(
        string $id,
        string $name = 'Ada Lovelace',
        string $email = 'ada@example.com',
        ?string $avatar = null,
        array $raw = [],
    ): void {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getName')->andReturn($name);
        $user->shouldReceive('getNickname')->andReturn(null);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getAvatar')->andReturn($avatar);
        $user->shouldReceive('getRaw')->andReturn($raw);

        Socialite::shouldReceive('driver')->with('pocketid')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($user);
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_page_renders_pocket_id_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Pocket-ID')
            ->assertSee(route('auth.redirect'));
    }

    public function test_callback_provisions_and_authenticates_a_new_user(): void
    {
        config(['services.pocketid.base_url' => 'https://id.example.com']);
        Storage::fake('local');
        Http::fake([
            '*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->fakeSocialiteUser(
            id: 'sub-123',
            name: 'Grace Hopper',
            email: 'grace@example.com',
            avatar: null,
            raw: ['picture' => 'https://id.example.com/avatars/grace.png'],
        );

        $this->get(route('auth.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'oidc_sub' => 'sub-123',
            'email' => 'grace@example.com',
            'name' => 'Grace Hopper',
        ]);

        // The avatar is downloaded and stored locally, never the remote URL.
        $user = User::firstWhere('oidc_sub', 'sub-123');
        $this->assertTrue(Str::startsWith($user->avatar, 'avatars/'));
        $this->assertNotSame('https://id.example.com/avatars/grace.png', $user->avatar);
        Storage::disk('local')->assertExists($user->avatar);
    }

    public function test_callback_succeeds_when_avatar_download_fails(): void
    {
        config(['services.pocketid.base_url' => 'https://id.example.com']);
        Storage::fake('local');
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->fakeSocialiteUser(
            id: 'sub-err',
            raw: ['picture' => 'https://id.example.com/avatars/x.png'],
        );

        $this->get(route('auth.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertNull(User::firstWhere('oidc_sub', 'sub-err')->avatar);
    }

    public function test_avatar_route_streams_the_stored_avatar(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['avatar' => 'avatars/7.png']);
        Storage::disk('local')->put('avatars/7.png', 'image-bytes');

        $this->actingAs($user)
            ->get(route('profile.avatar'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_avatar_route_returns_404_without_an_avatar(): void
    {
        $user = User::factory()->create(['avatar' => null]);

        $this->actingAs($user)
            ->get(route('profile.avatar'))
            ->assertNotFound();
    }

    public function test_guests_cannot_access_the_avatar_route(): void
    {
        $this->get(route('profile.avatar'))->assertRedirect(route('login'));
    }

    public function test_callback_matches_existing_user_by_oidc_sub_without_duplicating(): void
    {
        $existing = User::factory()->create([
            'oidc_sub' => 'sub-existing',
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->fakeSocialiteUser(
            id: 'sub-existing',
            name: 'New Name',
            email: 'new@example.com',
        );

        $this->get(route('auth.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_failed_callback_redirects_to_login_with_error(): void
    {
        Socialite::shouldReceive('driver')->with('pocketid')->andReturnSelf();
        Socialite::shouldReceive('user')->andThrow(new \RuntimeException('invalid state'));

        $this->get(route('auth.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('pocketid');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
