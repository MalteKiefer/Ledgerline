<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploreBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExploreBlobStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_upload_stores_bytes_and_records_ownership(): void
    {
        $user = $this->signIn();
        $id = $this->post(route('explore.upload'), ['file' => UploadedFile::fake()->create('track.bin', 8)])
            ->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('explore/'.$id);
        $this->assertDatabaseHas('explore_blobs', ['blob' => $id, 'user_id' => $user->id]);
    }

    public function test_raw_is_owner_scoped_and_cached_immutably(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('explore/'.$blob, 'ciphertext');
        ExploreBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('explore.raw', ['blob' => $blob]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');

        // Another user cannot read it, and a foreign delete is a uniform idempotent
        // no-op (no 403-vs-200 ownership oracle) that leaves the blob intact.
        $this->actingAs(User::factory()->create())->get(route('explore.raw', ['blob' => $blob]))->assertNotFound();
        $this->actingAs(User::factory()->create())->deleteJson(route('explore.blob.destroy', ['blob' => $blob]))->assertOk();
        $this->assertDatabaseHas('explore_blobs', ['blob' => $blob]);

        // The owner can delete it.
        $this->actingAs($user)->deleteJson(route('explore.blob.destroy', ['blob' => $blob]))->assertOk();
        $this->assertDatabaseMissing('explore_blobs', ['blob' => $blob]);
    }

    public function test_reconcile_frees_unreferenced_track_blobs(): void
    {
        $user = $this->signIn();
        $keep = (string) Str::uuid();
        $drop = (string) Str::uuid();
        foreach ([$keep, $drop] as $b) {
            Storage::disk(config('files.disk'))->put('explore/'.$b, 'x');
            // Older than the orphan grace so reconcile is allowed to reclaim it.
            ExploreBlob::create(['blob' => $b, 'user_id' => $user->id, 'size' => 1, 'created_at' => now()->subDays(2)]);
        }

        $this->postJson(route('explore.blobs.reconcile'), ['blobs' => [$keep]])->assertOk();

        $this->assertDatabaseHas('explore_blobs', ['blob' => $keep]);
        $this->assertDatabaseMissing('explore_blobs', ['blob' => $drop]);
    }

    public function test_explore_is_an_allowed_module_store_key(): void
    {
        $this->signIn();

        // The module store rejects unknown keys with a 404; `explore` must be allowed.
        $this->getJson(route('module-store.show', ['module' => 'explore']))->assertOk();
        $this->getJson(route('module-store.show', ['module' => 'nope']))->assertNotFound();
    }
}
