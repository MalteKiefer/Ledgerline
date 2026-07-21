<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\ModuleStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_store_manifest_is_private_to_its_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Alice seals her notes module in her own per-module store.
        $this->actingAs($alice)
            ->putJson(route('module-store.save', 'notes'), ['ciphertext' => 'alice-sealed-blob', 'version' => 0])
            ->assertOk();
        $this->actingAs($alice)->getJson(route('module-store.show', 'notes'))
            ->assertOk()->assertJson(['ciphertext' => 'alice-sealed-blob', 'version' => 1]);

        // Bob has his own empty manifest and never sees Alice's ciphertext.
        $this->actingAs($bob)->getJson(route('module-store.show', 'notes'))
            ->assertOk()->assertJson(['ciphertext' => null, 'version' => 0]);
        $this->assertSame($alice->id, ModuleStore::query()->where('ciphertext', 'alice-sealed-blob')->value('user_id'));
    }

    public function test_files_are_private_and_raw_download_is_owner_only(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('files/'.$blob, 'secret bytes');
        FileBlob::create(['blob' => $blob, 'user_id' => $alice->id, 'size' => 12, 'created_at' => now()]);

        // Owner can download their blob's ciphertext.
        $this->get(route('files.raw', ['blob' => $blob]))->assertOk();

        // Bob cannot fetch Alice's blob by guessing its UUID.
        $this->actingAs($bob);
        $this->get(route('files.raw', ['blob' => $blob]))->assertNotFound();
    }

    public function test_an_upload_is_owned_by_the_uploader(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $blob = $this->post(route('files.upload'), [
            'file' => UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf'),
        ])->assertCreated()->json('id');

        $this->assertSame($alice->id, (int) FileBlob::find($blob)->user_id);
    }
}
