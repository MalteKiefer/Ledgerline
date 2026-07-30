<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
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
        $this->getJson('/api/v1/files/entries')->assertStatus(401);
    }

    public function test_files_raw_is_owner_scoped_over_the_api(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $id = (int) $this->post('/api/v1/files/entries', [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'ciphertext-bytes'),
        ], $this->bearer($alice))->assertCreated()->json('file.id');

        $this->get('/api/v1/files/entries/'.$id.'/raw', $this->bearer($alice))->assertOk();
        // Reset the memoised guard so the next request re-resolves as Bob (a single-
        // process test artifact — each real request is fresh).
        $this->app['auth']->forgetGuards();
        $this->get('/api/v1/files/entries/'.$id.'/raw', $this->bearer($bob))->assertNotFound();
    }

    public function test_upload_over_the_api_is_owned_by_the_token_user(): void
    {
        Storage::fake(config('files.disk'));
        $user = User::factory()->create();

        $id = (int) $this->post('/api/v1/files/entries', [
            'file' => UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf'),
        ], $this->bearer($user))->assertCreated()->json('file.id');

        $this->assertSame($user->id, (int) FileEntry::withoutGlobalScopes()->findOrFail($id)->user_id);
    }

    public function test_usage_endpoints_report_the_token_users_bytes(): void
    {
        $user = User::factory()->create();
        (new FileEntry)->forceFill([
            'user_id' => $user->id, 'name' => 'f.bin', 'size' => 500, 'storage_path' => 'files/'.Str::uuid(),
        ])->save();

        // Usage now rides on the relational entries listing (files + versions).
        $this->getJson('/api/v1/files/entries', $this->bearer($user))
            ->assertOk()->assertJson(['usage' => ['used' => 500]]);
    }
}
