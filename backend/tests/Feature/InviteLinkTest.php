<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InviteLinkTest extends TestCase
{
    use RefreshDatabase;

    /** Create a valid link for a user and return [link, plaintextToken]. */
    private function link(User $user, ?\DateTimeInterface $expires = null, ?\DateTimeInterface $used = null): array
    {
        $token = InviteLink::newToken();
        $link = new InviteLink;
        $link->forceFill([
            'user_id' => $user->id,
            'token_hash' => InviteLink::hashToken($token),
            'expires_at' => $expires ?? now()->addDay(),
            'used_at' => $used,
        ])->save();

        return [$link, $token];
    }

    public function test_admin_can_create_a_link_and_gets_the_url(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $target = User::factory()->create();

        $this->post(route('settings.users.invite', $target), ['ttl_hours' => 24])
            ->assertRedirect()
            ->assertSessionHas('invite_url');

        $this->assertDatabaseCount('invite_links', 1);
    }

    public function test_non_admin_cannot_create_a_link(): void
    {
        $this->actingAs(User::factory()->create());
        $target = User::factory()->create();
        $this->post(route('settings.users.invite', $target), ['ttl_hours' => 24])->assertForbidden();
    }

    public function test_an_invalid_ttl_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $target = User::factory()->create();
        $this->post(route('settings.users.invite', $target), ['ttl_hours' => 5])->assertSessionHasErrors('ttl_hours');
    }

    public function test_a_valid_link_shows_the_set_password_form(): void
    {
        [$link, $token] = $this->link(User::factory()->create());
        $this->get(route('invite.show', ['invite' => $link->id, 'token' => $token]))->assertOk();
    }

    public function test_consuming_a_link_sets_the_password_and_signs_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        [$link, $token] = $this->link($user);

        $this->post(route('invite.store', ['invite' => $link->id, 'token' => $token]), [
            'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect(route('finance.index'));

        $user->refresh();
        $this->assertTrue(Hash::check('a-brand-new-passphrase', (string) $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($link->fresh()->used_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_link_is_single_use(): void
    {
        $user = User::factory()->create();
        [$link, $token] = $this->link($user, used: now());

        $this->get(route('invite.show', ['invite' => $link->id, 'token' => $token]))->assertRedirect(route('login'));
        $this->post(route('invite.store', ['invite' => $link->id, 'token' => $token]), [
            'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect(route('login'));
    }

    public function test_an_expired_link_is_rejected(): void
    {
        [$link, $token] = $this->link(User::factory()->create(), expires: now()->subHour());
        $this->get(route('invite.show', ['invite' => $link->id, 'token' => $token]))->assertRedirect(route('login'));
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        [$link] = $this->link(User::factory()->create());
        $this->get(route('invite.show', ['invite' => $link->id, 'token' => 'not-the-real-token']))->assertRedirect(route('login'));
    }

    public function test_the_password_floor_is_enforced(): void
    {
        [$link, $token] = $this->link(User::factory()->create());
        $this->post(route('invite.store', ['invite' => $link->id, 'token' => $token]), [
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
