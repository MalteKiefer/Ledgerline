<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Album;
use App\Models\ResourceShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSharingTest extends TestCase
{
    use RefreshDatabase;

    /** A shareable resource (album) owned by the given user. */
    private function albumOf(User $u, string $name): Album
    {
        $this->actingAs($u);
        $id = $this->postJson(route('gallery.albums.store'), ['name' => $name])->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();

        return Album::withoutGlobalScopes()->findOrFail($id);
    }

    public function test_a_third_user_still_cannot_see_the_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $album = $this->albumOf($alice, 'Private');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'albums', 'id' => $album->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();

        $this->actingAs($carol);
        $this->assertNull(Album::find($album->id));
    }

    public function test_only_the_owner_can_share_a_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $album = $this->albumOf($alice, 'Mine');

        // Bob (not the owner) cannot share Alice's album.
        $this->actingAs($bob)->postJson(route('shares.store'), [
            'type' => 'albums', 'id' => $album->id, 'email' => $alice->email, 'permission' => 'read',
        ])->assertForbidden();
    }

    public function test_internal_share_notifies_the_recipient_in_app(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $album = $this->albumOf($alice, 'Plan');

        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'albums', 'id' => $album->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated()->assertJsonStructure(['ok', 'id', 'link']);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $bob->id, 'category' => 'share']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $alice->id, 'category' => 'share']);
    }

    public function test_share_email_is_rejected_without_a_mail_server(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $album = $this->albumOf($alice, 'Plan');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'albums', 'id' => $album->id, 'email' => $bob->email, 'permission' => 'read',
        ]);
        $share = ResourceShare::firstOrFail();

        // No SMTP configured → the mail-share option is refused (offer copy link instead).
        $this->actingAs($alice)->postJson(route('shares.email', $share->id))->assertStatus(422);

        // A non-owner cannot trigger it either.
        $this->actingAs($bob)->postJson(route('shares.email', $share->id))->assertForbidden();
    }

    public function test_owner_can_revoke_a_share(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $album = $this->albumOf($alice, 'Temp');
        $this->actingAs($alice)->postJson(route('shares.store'), [
            'type' => 'albums', 'id' => $album->id, 'email' => $bob->email, 'permission' => 'read',
        ])->assertCreated();
        $share = ResourceShare::firstOrFail();

        // Bob can see it while the share exists.
        $this->actingAs($bob);
        $this->assertNotNull(Album::find($album->id));

        $this->actingAs($alice)->deleteJson(route('shares.destroy', $share->id))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs($bob);
        $this->assertNull(Album::find($album->id));
    }
}
