<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Export;
use App\Models\StoredFile;
use App\Services\Export\ExportArchiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportArchiverFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Export, 1: StoredFile[]}
     */
    private function makeFilesExport(int $userId, string $format): array
    {
        $files = [];
        $ids = [];
        foreach (['one.txt', 'two.txt'] as $name) {
            $file = StoredFile::create([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'blob' => (string) Str::uuid(),
                'size' => 5,
            ]);
            Storage::disk(config('files.disk'))->put('files/'.$file->blob, 'hello');
            $files[] = $file;
            $ids[] = $file->id;
        }

        // Ownership is normally assigned from auth; set it explicitly and persist
        // without the model events so the exact user_id/format we want is stored.
        $export = new Export([
            'source' => 'files',
            'format' => $format,
            'title' => 'archive',
            'status' => 'queued',
            'item_count' => 2,
            'payload' => ['file_ids' => $ids, 'folder_ids' => []],
        ]);
        $export->user_id = $userId;
        $export->saveQuietly();

        return [$export, $files];
    }

    public function test_tar_export_produces_a_single_stored_part(): void
    {
        Storage::fake(config('files.disk'));

        [$export] = $this->makeFilesExport(1, 'tar');

        $parts = app(ExportArchiver::class)->build($export, 0);

        $this->assertCount(1, $parts);
        $this->assertStringEndsWith('.tar', $parts[0]['name']);
        $this->assertSame("exports/1/{$export->id}/export.tar", $parts[0]['path']);
        Storage::disk(config('files.disk'))->assertExists($parts[0]['path']);
        $this->assertGreaterThan(0, $parts[0]['size']);
    }

    public function test_targz_export_produces_a_gzip_archive(): void
    {
        Storage::fake(config('files.disk'));

        [$export] = $this->makeFilesExport(2, 'targz');

        $parts = app(ExportArchiver::class)->build($export, 0);

        $this->assertCount(1, $parts);
        $this->assertStringEndsWith('.tar.gz', $parts[0]['name']);
        $this->assertSame("exports/2/{$export->id}/export.tar.gz", $parts[0]['path']);
        Storage::disk(config('files.disk'))->assertExists($parts[0]['path']);

        // gzip magic number: 0x1f 0x8b
        $bytes = Storage::disk(config('files.disk'))->get($parts[0]['path']);
        $this->assertSame("\x1f\x8b", substr($bytes, 0, 2));
    }
}
