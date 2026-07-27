<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_profile(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_profile_shows_the_account_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        // Name + email head the hub and are detailed on the account sub-page.
        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('grace@example.com');
        $this->actingAs($user)
            ->get(route('profile.account'))
            ->assertOk()
            ->assertSee('grace@example.com');
    }

    public function test_profile_renders_the_avatar_when_present(): void
    {
        $user = User::factory()->create(['avatar' => 'avatars/1.png']);

        // The hero shows the avatar; its provenance is detailed on the account sub-page.
        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(route('profile.avatar'));
        $this->actingAs($user)
            ->get(route('profile.account'))
            ->assertOk()
            ->assertSee('object storage');
    }
}
