<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Album;
use App\Models\PublicShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicShareHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function ownedAlbumShare(User $owner): PublicShare
    {
        $this->actingAs($owner);
        $album = Album::create(['user_id' => $owner->id, 'name' => 'Trip']);

        return PublicShare::forResource($album, $owner->id);
    }

    public function test_store_sets_expiry_and_password(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);
        $album = Album::create(['user_id' => $alice->id, 'name' => 'A']);

        $this->postJson(route('public-share.store'), [
            'type' => 'albums', 'id' => $album->id, 'expires_in' => 3600, 'password' => 'secret',
        ])->assertCreated()->assertJsonPath('has_password', true);

        $share = PublicShare::firstWhere('shareable_id', $album->id);
        $this->assertNotNull($share->expires_at);
        $this->assertTrue($share->hasPassword());
        $this->assertNotSame('secret', $share->password); // hashed
    }

    public function test_expired_album_link_returns_410(): void
    {
        $share = $this->ownedAlbumShare(User::factory()->create());
        $share->update(['expires_at' => now()->subMinute()]);
        $this->app['auth']->forgetGuards();

        $this->get(route('public-share.album', $share->token))->assertStatus(410);
    }

    public function test_password_gate_blocks_then_unlocks(): void
    {
        $share = $this->ownedAlbumShare(User::factory()->create());
        $share->update(['password' => Hash::make('open-sesame')]);
        $this->app['auth']->forgetGuards();

        // Locked: shows the password prompt, not the album.
        $this->get(route('public-share.album', $share->token))
            ->assertOk()->assertSee(__('shares.public_password_prompt'));

        // Wrong password: still prompted.
        $this->post(route('public-share.album.unlock', $share->token), ['password' => 'nope'])
            ->assertOk()->assertSee(__('shares.public_wrong_password'));

        // Correct password: unlocked for the session.
        $this->post(route('public-share.album.unlock', $share->token), ['password' => 'open-sesame'])
            ->assertRedirect(route('public-share.album', $share->token));
        $this->get(route('public-share.album', $share->token))->assertOk()->assertSee('Trip');
    }

    public function test_rotate_changes_token_and_is_owner_only(): void
    {
        $owner = User::factory()->create();
        $share = $this->ownedAlbumShare($owner);
        $old = $share->token;

        $this->actingAs($owner)->postJson(route('public-share.rotate', $share))->assertOk();
        $this->assertNotSame($old, $share->fresh()->token);

        $this->actingAs(User::factory()->create())
            ->postJson(route('public-share.rotate', $share))->assertForbidden();
    }
}
