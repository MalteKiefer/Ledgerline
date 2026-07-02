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

    public function test_profile_shows_pocket_id_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'oidc_sub' => 'sub-abc-123',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('grace@example.com')
            ->assertSee('sub-abc-123');
    }

    public function test_profile_renders_the_avatar_when_present(): void
    {
        $user = User::factory()->create(['avatar' => 'avatars/1.png']);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(route('profile.avatar'))
            ->assertSee('object storage');
    }
}
