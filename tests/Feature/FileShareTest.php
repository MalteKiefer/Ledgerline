<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\PublicShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileShareTest extends TestCase
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
        Storage::disk(config('files.disk'))->put('files/'.$ref, 'ciphertext');
        FileBlob::create(['blob' => $ref, 'user_id' => $owner->id, 'size' => 10, 'created_at' => now()]);

        return $ref;
    }

    private function createShare(User $owner, array $refs, array $extra = []): string
    {
        return $this->actingAs($owner)->postJson(route('files.shares.store'), array_merge([
            'kind' => 'file',
            'sealed_manifest' => 'SEALED',
            'blob_refs' => $refs,
            'allow_download' => true,
        ], $extra))->assertOk()->json('token');
    }

    public function test_file_share_serves_from_the_files_disk(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref]);

        $this->assertSame('file', PublicShare::where('token', $token)->firstOrFail()->kind);

        $this->getJson(route('public.share.meta', $token))->assertOk()->assertJson(['found' => true, 'unlocked' => true]);
        $this->getJson(route('public.share.manifest', $token))->assertOk()->assertJson(['sealed' => 'SEALED']);
        // The blob route resolves the files/ prefix + FileBlob ledger from the kind.
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $ref]))->assertOk();
    }

    public function test_folder_share_kind_is_accepted_and_file_only_kinds_enforced(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);

        $this->createShare($owner, [$ref], ['kind' => 'folder']); // ok
        // A gallery kind is rejected by the files endpoint.
        $this->actingAs($owner)->postJson(route('files.shares.store'), [
            'kind' => 'gallery_album', 'sealed_manifest' => 'X', 'blob_refs' => [$ref], 'allow_download' => true,
        ])->assertStatus(422);
    }

    public function test_blob_route_rejects_unlisted_and_foreign_refs(): void
    {
        $owner = User::factory()->create();
        $listed = $this->makeBlob($owner);
        $unlisted = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$listed]);

        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $listed]))->assertOk();
        $this->get(route('public.share.blob', ['token' => $token, 'ref' => $unlisted]))->assertNotFound();
    }

    public function test_password_gate_and_expiry(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref], ['password' => 'longenough']);

        $this->getJson(route('public.share.manifest', $token))->assertForbidden();
        $this->postJson(route('public.share.unlock', $token), ['password' => 'nope'])->assertStatus(422);
        $this->postJson(route('public.share.unlock', $token), ['password' => 'longenough'])->assertOk();
        $this->getJson(route('public.share.manifest', $token))->assertOk();

        PublicShare::where('token', $token)->update(['expires_at' => now()->subMinute()]);
        $this->getJson(route('public.share.meta', $token))->assertStatus(410);
    }

    public function test_management_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $ref = $this->makeBlob($owner);
        $token = $this->createShare($owner, [$ref]);

        $this->actingAs(User::factory()->create())->deleteJson(route('files.shares.destroy', $token))->assertNotFound();
        $this->assertNotNull(PublicShare::where('token', $token)->first());
        $this->actingAs($owner)->deleteJson(route('files.shares.destroy', $token))->assertOk();
        $this->assertNull(PublicShare::where('token', $token)->first());
    }
}
