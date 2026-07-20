<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiFileSharesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device')->plainTextToken];
    }

    private function makeBlob(User $owner): string
    {
        $ref = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$ref, 'ciphertext');
        FileBlob::create(['blob' => $ref, 'user_id' => $owner->id, 'size' => 10, 'created_at' => now()]);

        return $ref;
    }

    public function test_device_token_can_create_and_revoke_a_file_share(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);
        $ref = $this->makeBlob($user);

        $token = $this->postJson(route('api.files.shares.store'), [
            'kind' => 'file',
            'sealed_manifest' => 'SEALED',
            'blob_refs' => [$ref],
            'allow_download' => true,
        ], $h)->assertOk()->json('token');

        $this->assertNotEmpty($token);

        $this->deleteJson(route('api.files.shares.destroy', $token), [], $h)->assertOk();
    }

    public function test_device_token_can_create_a_folder_share(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);
        $ref = $this->makeBlob($user);

        $token = $this->postJson(route('api.files.shares.store'), [
            'kind' => 'folder',
            'sealed_manifest' => 'SEALED',
            'blob_refs' => [$ref],
            'allow_download' => true,
        ], $h)->assertOk()->json('token');

        $this->assertNotEmpty($token);
    }

    public function test_invalid_kind_is_rejected(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);
        $ref = $this->makeBlob($user);

        $this->postJson(route('api.files.shares.store'), [
            'kind' => 'gallery_album',
            'sealed_manifest' => 'X',
            'blob_refs' => [$ref],
            'allow_download' => true,
        ], $h)->assertStatus(422);
    }

    public function test_destroy_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $h = $this->bearer($owner);
        $ref = $this->makeBlob($owner);

        $token = $this->postJson(route('api.files.shares.store'), [
            'kind' => 'file',
            'sealed_manifest' => 'SEALED',
            'blob_refs' => [$ref],
            'allow_download' => true,
        ], $h)->assertOk()->json('token');

        // Reset the memoised guard so the next request re-resolves as $other
        // (single-process test artefact — each real request is fresh).
        $this->app['auth']->forgetGuards();

        // Another device-token user cannot revoke it.
        $other = User::factory()->create();
        $this->deleteJson(route('api.files.shares.destroy', $token), [], $this->bearer($other))->assertNotFound();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(route('api.files.shares.store'), [
            'kind' => 'file',
            'sealed_manifest' => 'SEALED',
            'blob_refs' => [],
            'allow_download' => true,
        ])->assertUnauthorized();
    }
}
