<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Export;
use App\Models\Photo;
use App\Models\User;
use App\Services\Export\ExportArchiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportArchiverFormatTest extends TestCase
{
    use RefreshDatabase;

    // Files are end-to-end encrypted (no server-side export); the archive format
    // logic is source-agnostic, so it is exercised through a GALLERY export.
    private function makeFilesExport(int $userId, string $format): array
    {
        User::factory()->create(['id' => $userId]);
        $ids = [];
        foreach (['one.jpg', 'two.jpg'] as $name) {
            $blob = 'photos/'.Str::uuid().'.jpg';
            Storage::disk(config('files.disk'))->put($blob, 'hello');
            $photo = Photo::factory()->create(['name' => $name, 'disk_path' => $blob, 'size' => 5, 'uploaded_by' => $userId]);
            $ids[] = $photo->id;
        }

        $export = new Export([
            'source' => 'gallery',
            'format' => $format,
            'variant' => 'original',
            'title' => 'archive',
            'status' => 'queued',
            'item_count' => 2,
            'payload' => ['photo_ids' => $ids],
        ]);
        $export->user_id = $userId;
        $export->saveQuietly();

        return [$export, $ids];
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
