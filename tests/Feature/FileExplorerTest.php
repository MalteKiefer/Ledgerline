<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileExplorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_upload_stores_every_file(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('files.store.general'), [
            'files' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            ],
        ])->assertRedirect(route('files.index', ['folder' => null]));

        $this->assertSame(3, File::count());
    }

    public function test_rename_sets_the_display_title(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create(['name' => 'raw.pdf', 'title' => null]);

        $this->put(route('files.rename', $file), ['title' => 'Signed contract'])->assertRedirect();

        $this->assertSame('Signed contract', $file->fresh()->title);
    }
}
