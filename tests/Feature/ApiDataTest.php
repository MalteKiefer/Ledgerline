<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactBlob;
use App\Models\FileBlob;
use App\Models\GalleryStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiDataTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device')->plainTextToken];
    }

    public function test_data_endpoints_require_a_bearer(): void
    {
        $this->getJson('/api/v1/vault')->assertStatus(401);
        $this->getJson('/api/v1/store/notes')->assertStatus(401);
        $this->getJson('/api/v1/gallery/store')->assertStatus(401);
        $this->getJson('/api/v1/files/raw/'.Str::uuid())->assertStatus(401);
    }

    public function test_store_roundtrip_returns_only_the_opaque_envelope(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);

        $this->putJson('/api/v1/store/notes', ['ciphertext' => 'sealed-blob', 'version' => 0], $h)->assertOk();
        $res = $this->getJson('/api/v1/store/notes', $h)->assertOk();

        // Zero-knowledge: the only fields are the ciphertext + version — no plaintext.
        $this->assertSame(['ciphertext', 'version'], array_keys($res->json()));
        $res->assertJson(['ciphertext' => 'sealed-blob', 'version' => 1]);
    }

    public function test_files_raw_is_owner_scoped_over_the_api(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$blob, 'ciphertext-bytes');
        FileBlob::create(['blob' => $blob, 'user_id' => $alice->id, 'size' => 16, 'created_at' => now()]);

        $this->get('/api/v1/files/raw/'.$blob, $this->bearer($alice))->assertOk();
        // Reset the memoised guard so the next request re-resolves as Bob (a single-
        // process test artifact — each real request is fresh).
        $this->app['auth']->forgetGuards();
        $this->get('/api/v1/files/raw/'.$blob, $this->bearer($bob))->assertNotFound();
    }

    public function test_contact_avatar_blobs_are_reachable_and_owner_scoped_over_the_api(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->getJson('/api/v1/contacts/usage')->assertStatus(401); // needs a bearer

        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('contacts/'.$blob, 'avatar-ciphertext');
        ContactBlob::create(['blob' => $blob, 'user_id' => $alice->id, 'size' => 8, 'created_at' => now()]);

        $this->get('/api/v1/contacts/raw/'.$blob, $this->bearer($alice))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get('/api/v1/contacts/raw/'.$blob, $this->bearer($bob))->assertNotFound();
    }

    public function test_upload_over_the_api_is_owned_by_the_token_user(): void
    {
        Storage::fake(config('files.disk'));
        $user = User::factory()->create();

        $blob = $this->post('/api/v1/files/upload', [
            'file' => UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf'),
        ], $this->bearer($user))->assertCreated()->json('id');

        $this->assertSame($user->id, (int) FileBlob::find($blob)->user_id);
    }

    public function test_gallery_store_is_opaque_and_private(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->putJson('/api/v1/gallery/store', ['ciphertext' => 'alice-gallery', 'version' => 0], $this->bearer($alice))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/gallery/store', $this->bearer($bob))
            ->assertOk()->assertJson(['ciphertext' => null, 'version' => 0]);
        $this->assertSame($alice->id, GalleryStore::query()->where('ciphertext', 'alice-gallery')->value('user_id'));
    }

    public function test_usage_endpoints_report_the_token_users_bytes(): void
    {
        $user = User::factory()->create();
        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 500, 'created_at' => now()]);

        $this->getJson('/api/v1/files/usage', $this->bearer($user))
            ->assertOk()->assertJson(['used' => 500]);
    }
}
