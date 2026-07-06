<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_renders_with_a_last_login_timestamp(): void
    {
        // Regression: last_login_at was uncast (string), so ?->format() called
        // format() on a string and 500'd the profile page.
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'last_login_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee($user->last_login_at->format('Y-m-d'), false);
    }
}
