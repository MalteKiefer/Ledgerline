<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NoteShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    private function makeShare(array $attributes = []): NoteShare
    {
        return NoteShare::create(array_merge([
            'cipher' => 'Y2lwaGVy',
            'nonce' => 'bm9uY2U=',
            'has_password' => false,
            'expires_at' => now()->addDay(),
        ], $attributes));
    }

    public function test_guests_cannot_create_a_share(): void
    {
        $this->post(route('shares.store'), [
            'cipher' => 'Y2lwaGVy', 'nonce' => 'bm9uY2U=', 'expires_in' => 86400, 'has_password' => false,
        ])->assertRedirect(route('login'));
    }

    public function test_store_creates_a_share_with_the_right_expiry(): void
    {
        $this->signIn();

        $response = $this->postJson(route('shares.store'), [
            'cipher' => 'Y2lwaGVy',
            'nonce' => 'bm9uY2U=',
            'expires_in' => 86400,
            'has_password' => false,
        ])->assertCreated()->assertJsonStructure(['id', 'url', 'expires_at']);

        $share = NoteShare::query()->firstOrFail();
        $this->assertFalse($share->has_password);
        $this->assertEqualsWithDelta(now()->addSeconds(86400)->timestamp, $share->expires_at->timestamp, 5);
        $this->assertSame($share->id, $response->json('id'));
    }

    public function test_store_rejects_a_disallowed_lifetime(): void
    {
        $this->signIn();

        $this->from(route('notes.index'))->post(route('shares.store'), [
            'cipher' => 'Y2lwaGVy', 'nonce' => 'bm9uY2U=', 'expires_in' => 999, 'has_password' => false,
        ])->assertRedirect()->assertSessionHasErrors('expires_in');

        $this->assertDatabaseCount('note_shares', 0);
    }

    public function test_password_shares_require_the_wrap_fields(): void
    {
        $this->signIn();

        $this->from(route('notes.index'))->post(route('shares.store'), [
            'cipher' => 'Y2lwaGVy', 'nonce' => 'bm9uY2U=', 'expires_in' => 3600, 'has_password' => true,
        ])->assertRedirect()->assertSessionHasErrors(['wrapped_key', 'wrap_salt', 'wrap_nonce', 'wrap_ops', 'wrap_mem']);

        $this->assertDatabaseCount('note_shares', 0);
    }

    public function test_the_public_viewer_is_reachable_without_login(): void
    {
        $share = $this->makeShare();

        $this->get(route('shares.show', $share))->assertOk();
    }

    public function test_data_returns_only_ciphertext_and_counts_the_view(): void
    {
        $share = $this->makeShare();

        $this->getJson(route('shares.data', $share))
            ->assertOk()
            ->assertJson(['cipher' => 'Y2lwaGVy', 'nonce' => 'bm9uY2U=', 'has_password' => false])
            ->assertJsonMissing(['title', 'content']);

        $this->assertSame(1, $share->fresh()->views);
    }

    public function test_password_share_data_exposes_the_wrap_but_no_plaintext(): void
    {
        $share = $this->makeShare([
            'has_password' => true,
            'wrapped_key' => 'd3JhcHBlZA==',
            'wrap_salt' => 'c2FsdA==',
            'wrap_nonce' => 'd25vbmNl',
            'wrap_ops' => 2,
            'wrap_mem' => 67108864,
        ]);

        $this->getJson(route('shares.data', $share))
            ->assertOk()
            ->assertJson(['has_password' => true, 'wrapped_key' => 'd3JhcHBlZA==', 'wrap_salt' => 'c2FsdA==']);
    }

    public function test_an_expired_share_is_gone_and_deleted(): void
    {
        $share = $this->makeShare(['expires_at' => now()->subMinute()]);

        $this->getJson(route('shares.data', $share))->assertStatus(410);
        $this->assertDatabaseMissing('note_shares', ['id' => $share->id]);
    }

    public function test_a_view_limit_burns_the_share(): void
    {
        $share = $this->makeShare(['max_views' => 1]);

        // First retrieval succeeds and counts.
        $this->getJson(route('shares.data', $share))->assertOk();
        // Second retrieval is over the limit: gone and removed.
        $this->getJson(route('shares.data', $share))->assertStatus(410);
        $this->assertDatabaseMissing('note_shares', ['id' => $share->id]);
    }

    public function test_owner_can_revoke_a_share(): void
    {
        $this->signIn();
        $share = $this->makeShare();

        $this->deleteJson(route('shares.destroy', $share))->assertOk();
        $this->assertDatabaseMissing('note_shares', ['id' => $share->id]);
    }

    public function test_guests_cannot_revoke_a_share(): void
    {
        $share = $this->makeShare();

        $this->delete(route('shares.destroy', $share))->assertRedirect(route('login'));
        $this->assertDatabaseHas('note_shares', ['id' => $share->id]);
    }

    public function test_prune_deletes_only_expired_shares(): void
    {
        $live = $this->makeShare(['expires_at' => now()->addHour()]);
        $dead = $this->makeShare(['expires_at' => now()->subHour()]);

        $this->artisan('shares:prune')->assertSuccessful();

        $this->assertDatabaseHas('note_shares', ['id' => $live->id]);
        $this->assertDatabaseMissing('note_shares', ['id' => $dead->id]);
    }
}
