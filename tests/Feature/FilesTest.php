<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
        $this->get(route('files.data'))->assertRedirect(route('login'));
    }

    public function test_the_page_loads_without_a_vault(): void
    {
        $this->signIn();
        $this->get(route('files.index'))->assertOk();
        $this->getJson(route('files.data'))->assertOk()->assertJson(['folders' => [], 'files' => []]);
    }

    public function test_upload_stores_a_file_and_sync_writes_rows(): void
    {
        $this->signIn();
        Storage::fake('files');
        config(['files.disk' => 'files']);

        $res = $this->post(route('files.upload'), ['file' => UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf')])
            ->assertCreated();
        $blob = $res->json('id');
        Storage::disk('files')->assertExists('files/'.$blob);

        $fileId = (string) Str::uuid();
        $this->putJson(route('files.sync'), [
            'folders' => [],
            'files' => [[
                'id' => $fileId, 'blob' => $blob, 'name' => 'doc.pdf',
                'mime' => 'application/pdf', 'size' => 12, 'folder' => null, 'tags' => ['work'],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('files', ['id' => $fileId, 'name' => 'doc.pdf']);
        $this->assertSame(['work'], StoredFile::find($fileId)->tags);
    }

    public function test_sync_deletes_rows_missing_from_the_manifest(): void
    {
        $this->signIn();
        StoredFile::create(['id' => (string) Str::uuid(), 'name' => 'gone', 'blob' => (string) Str::uuid(), 'mime' => 'text/plain', 'size' => 1]);

        $this->putJson(route('files.sync'), ['folders' => [], 'files' => []])->assertOk();

        $this->assertSame(0, StoredFile::count());
    }

    public function test_files_appear_in_global_search(): void
    {
        $this->signIn();
        StoredFile::create(['id' => (string) Str::uuid(), 'name' => 'searchabledoc.pdf', 'blob' => (string) Str::uuid(), 'mime' => 'application/pdf', 'size' => 3]);

        $this->getJson(route('search.suggest', ['q' => 'searchabledoc']))
            ->assertOk()->assertSee('searchabledoc.pdf');
    }
}
