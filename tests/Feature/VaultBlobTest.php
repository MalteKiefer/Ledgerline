<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultBlobTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_touch_blobs(): void
    {
        $this->post(route('vault.blobs.store'))->assertRedirect(route('login'));
    }

    public function test_a_blob_roundtrips_untouched(): void
    {
        Storage::fake('files');
        $this->signIn();

        $res = $this->post(route('vault.blobs.store'), [
            'blob' => UploadedFile::fake()->createWithContent('blob', 'padded-ciphertext'),
        ])->assertCreated();

        $id = $res->json('id');
        $this->assertTrue(Str::isUuid($id));
        Storage::disk('files')->assertExists('vault/'.$id);

        $download = $this->get(route('vault.blobs.show', $id));
        $download->assertOk();
        $this->assertSame('padded-ciphertext', $download->streamedContent());
        $this->assertSame('application/octet-stream', $download->headers->get('Content-Type'));

        $this->delete(route('vault.blobs.destroy', $id))->assertOk();
        Storage::disk('files')->assertMissing('vault/'.$id);
    }

    public function test_blob_ids_must_be_uuids(): void
    {
        Storage::fake('files');
        $this->signIn();

        // A traversal-looking id can never resolve to a path.
        $this->get('/vault/blobs/..%2F..%2Fsecret')->assertNotFound();
        $this->get(route('vault.blobs.show', 'not-a-uuid'))->assertNotFound();
    }

    public function test_missing_blobs_404(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->get(route('vault.blobs.show', (string) Str::uuid()))->assertNotFound();
    }
}
