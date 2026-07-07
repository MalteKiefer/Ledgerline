<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExtractFileText;
use App\Models\StoredFile;
use App\Models\User;
use App\Search\Providers\FileSearchProvider;
use App\Services\Files\FileTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileTextSearchTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u, string $name, string $mime, string $body): StoredFile
    {
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, $body);
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => $name, 'blob' => $blob, 'size' => strlen($body), 'mime' => $mime])->save();

        return $f;
    }

    public function test_text_file_content_extracted_and_saved_by_job(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u, 'notes.txt', 'text/plain', 'The quick brown Kangaroo jumps.');

        $this->assertStringContainsString('Kangaroo', app(FileTextExtractor::class)->extract($f));

        (new ExtractFileText($f->id, $f->blob))->handle(app(FileTextExtractor::class));
        $this->assertStringContainsString('Kangaroo', StoredFile::withoutGlobalScopes()->find($f->id)->content);
    }

    public function test_global_search_matches_content(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $this->actingAs($u);
        $f = $this->file($u, 'invoice.txt', 'text/plain', 'irrelevant name');
        $f->forceFill(['content' => 'Zebra Corporation invoice total 4321'])->save();

        $results = app(FileSearchProvider::class)->search('Zebra', 10);
        $this->assertNotEmpty($results);
        $this->assertSame('invoice.txt', $results[0]->title);
    }
}
