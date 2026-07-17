<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryBlob;
use App\Models\PublicShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function makeBlob(User $owner): string
    {
        $ref = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('gallery/'.$ref, 'ciphertext');
        GalleryBlob::create(['blob' => $ref, 'user_id' => $owner->id, 'size' => 10, 'created_at' => now()]);

        return $ref;
    }

    private function createShare(User $owner, array $refs, array $extra = []): string
    {
        return $this->actingAs($owner)->postJson(route('gallery.shares.store'), array_merge([
            'sealed_manifest' => 'SEALED',
            'blob_refs' => $refs,
            'allow_download' => false,
        ], $extra))->assertOk()->json('token');
    }

    public function test_owner_creates_a_share_and_public_can_read_it(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref]);

        $share = PublicShare::where('token', $token)->firstOrFail();
        $this->assertSame([$ref], $share->blob_refs);
        $this->assertNull($share->password_hash);

        // Public, unauthenticated access (a fresh session, not the owner's).
        $this->getJson(route('public.share.meta', $token))
            ->assertOk()->assertJson(['found' => true, 'needsPassword' => false, 'unlocked' => true]);
        $this->getJson(route('public.share.manifest', $token))
            ->assertOk()->assertJson(['sealed' => 'SEALED']);
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $ref]))->assertOk();

        $this->assertSame(1, (int) $share->fresh()->views);
    }

    public function test_blob_route_only_serves_allow_listed_owned_refs(): void
    {
        $owner = User::factory()->create();
        $listed = $this->makeBlob($owner);
        $unlisted = $this->makeBlob($owner);              // owned but not in the link
        $token = $this->createShare($owner, [$listed]);

        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $listed]))->assertOk();
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $unlisted]))->assertNotFound();
        // A ref in the allow-list but owned by nobody in the ledger is refused too.
        $ghost = (string) Str::uuid();
        $token2 = $this->createShare($owner, [$ghost]);
        $this->get(route('public.share.blob', ['token' => $token2, 'ref' => $ghost]))->assertNotFound();
    }

    public function test_password_gate_blocks_until_unlocked(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref], ['password' => 'hunter2secret']);

        $this->getJson(route('public.share.meta', $token))
            ->assertOk()->assertJson(['needsPassword' => true, 'unlocked' => false]);
        $this->getJson(route('public.share.manifest', $token))->assertForbidden();
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $ref]))->assertForbidden();

        $this->postJson(route('public.share.unlock', $token), ['password' => 'wrong'])->assertStatus(422);
        $this->postJson(route('public.share.unlock', $token), ['password' => 'hunter2secret'])->assertOk();

        $this->getJson(route('public.share.manifest', $token))->assertOk();
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $ref]))->assertOk();
    }

    public function test_expired_share_is_not_served(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref]);
        PublicShare::where('token', $token)->update(['expires_at' => now()->subMinute()]);

        $this->getJson(route('public.share.meta', $token))->assertStatus(410);
        $this->getJson(route('public.share.manifest', $token))->assertNotFound();
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $ref]))->assertNotFound();
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->getJson(route('public.share.meta', 'doesnotexist'))->assertStatus(404);
    }

    public function test_share_management_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref]);
        $other = User::factory()->create();

        $this->actingAs($other)->putJson(route('gallery.shares.update', $token), [
            'sealed_manifest' => 'X', 'blob_refs' => [$ref], 'allow_download' => false,
        ])->assertNotFound();
        $this->actingAs($other)->deleteJson(route('gallery.shares.destroy', $token))->assertNotFound();
        $this->assertNotNull(PublicShare::where('token', $token)->first());

        $this->actingAs($owner)->deleteJson(route('gallery.shares.destroy', $token))->assertOk();
        $this->assertNull(PublicShare::where('token', $token)->first());
    }
}
