<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_files_are_private_and_raw_download_is_owner_only(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $id = (int) $this->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'secret bytes'),
        ])->assertCreated()->json('file.id');

        // Owner can download their file's bytes.
        $this->get(route('files.rel.raw', $id))->assertOk();

        // Bob cannot resolve Alice's file (owner global scope) → 404.
        $this->actingAs($bob);
        $this->get(route('files.rel.raw', $id))->assertNotFound();
    }

    public function test_an_upload_is_owned_by_the_uploader(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $id = (int) $this->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf'),
        ])->assertCreated()->json('file.id');

        $this->assertSame($alice->id, (int) FileEntry::withoutGlobalScopes()->findOrFail($id)->user_id);
    }
}
