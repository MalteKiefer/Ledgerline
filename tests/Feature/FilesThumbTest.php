<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesThumbTest extends TestCase
{
    use RefreshDatabase;

    public function test_thumbnail_generated_for_owned_image(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        // A tiny real PNG.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, $png);
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'p.png', 'blob' => $blob, 'size' => strlen($png), 'mime' => 'image/png'])->save();

        $this->actingAs($u)->get(route('files.thumb', $blob))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        Storage::disk('files')->assertExists('thumbs/'.$blob.'.jpg');
    }

    public function test_thumbnail_404_for_non_image(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'a.txt', 'blob' => $blob, 'size' => 1, 'mime' => 'text/plain'])->save();

        $this->actingAs($u)->get(route('files.thumb', $blob))->assertNotFound();
    }
}
