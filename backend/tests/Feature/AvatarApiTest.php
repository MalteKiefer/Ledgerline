<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device')->plainTextToken];
    }

    public function test_avatar_requires_a_bearer(): void
    {
        $this->get('/api/v1/avatar')->assertStatus(401);
    }

    public function test_me_reports_has_avatar_false_and_avatar_404_when_none_stored(): void
    {
        $user = User::factory()->create(['avatar' => null]);
        $h = $this->bearer($user);

        $this->getJson('/api/v1/me', $h)->assertOk()->assertJsonPath('user.has_avatar', false);
        $this->get('/api/v1/avatar', $h)->assertStatus(404);
    }

    public function test_me_reports_has_avatar_true_and_avatar_streams_when_stored(): void
    {
        Storage::fake(config('files.disk'));
        Storage::disk(config('files.disk'))->put('avatars/u1.png', 'PNGDATA');

        $user = User::factory()->create(['avatar' => 'avatars/u1.png']);
        $h = $this->bearer($user);

        $this->getJson('/api/v1/me', $h)->assertOk()->assertJsonPath('user.has_avatar', true);
        $this->get('/api/v1/avatar', $h)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
