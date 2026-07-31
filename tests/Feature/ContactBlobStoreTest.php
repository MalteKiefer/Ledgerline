<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactBlobStoreTest extends TestCase
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
        $id = $this->post(route('contacts.upload'), ['file' => UploadedFile::fake()->create('enc.bin', 8)])
            ->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('contacts/'.$id);
        $this->assertDatabaseHas('contact_blobs', ['blob' => $id, 'user_id' => $user->id]);
    }

    public function test_raw_is_owner_scoped_and_cached_immutably(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('contacts/'.$blob, 'ciphertext');
        ContactBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('contacts.raw', ['blob' => $blob]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');

        // Another user cannot read it, and a foreign delete is a uniform idempotent
        // no-op (no 403-vs-200 ownership oracle) that leaves the blob intact.
        $this->actingAs(User::factory()->create())->get(route('contacts.raw', ['blob' => $blob]))->assertNotFound();
        $this->actingAs(User::factory()->create())->deleteJson(route('contacts.blob.destroy', ['blob' => $blob]))->assertOk();
        $this->assertDatabaseHas('contact_blobs', ['blob' => $blob]);

        // The owner can delete it.
        $this->actingAs($user)->deleteJson(route('contacts.blob.destroy', ['blob' => $blob]))->assertOk();
        $this->assertDatabaseMissing('contact_blobs', ['blob' => $blob]);
    }

    public function test_reconcile_frees_unreferenced_avatar_blobs(): void
    {
        $user = $this->signIn();
        $keep = (string) Str::uuid();
        $drop = (string) Str::uuid();
        foreach ([$keep, $drop] as $b) {
            Storage::disk(config('files.disk'))->put('contacts/'.$b, 'x');
            // Older than the orphan grace so reconcile is allowed to reclaim it.
            ContactBlob::create(['blob' => $b, 'user_id' => $user->id, 'size' => 1, 'created_at' => now()->subDays(2)]);
        }

        $this->postJson(route('contacts.blobs.reconcile'), ['blobs' => [$keep]])->assertOk();

        $this->assertDatabaseHas('contact_blobs', ['blob' => $keep]);
        $this->assertDatabaseMissing('contact_blobs', ['blob' => $drop]);
    }
}
