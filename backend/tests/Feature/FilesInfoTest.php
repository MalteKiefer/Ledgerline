<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Files info panel: extracted metadata (STL geometry via the sync-queue observer,
 * no external binary needed), same-checksum duplicate detection, folder path and
 * the info endpoint's owner scope.
 */
class FilesInfoTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal 1-triangle binary STL with a known 10×20×0 bounding box. */
    private function binaryStl(): string
    {
        $header = str_repeat("\0", 80);
        $tri = pack('V', 1); // triangle count
        $tri .= pack('g3', 0, 0, 1); // normal
        $tri .= pack('g3', 0, 0, 0); // v1
        $tri .= pack('g3', 10, 0, 0); // v2
        $tri .= pack('g3', 0, 20, 0); // v3
        $tri .= pack('v', 0); // attribute byte count

        return $header.$tri;
    }

    private function makeFile(User $user, string $name, string $path, string $mime, string $sha, string $bytes): FileEntry
    {
        Storage::disk((string) config('files.disk'))->put($path, $bytes);

        return FileEntry::forceCreate([
            'user_id' => $user->id, 'name' => $name, 'storage_path' => $path,
            'mime' => $mime, 'size' => strlen($bytes), 'sha256' => $sha, 'version' => 0,
        ]);
    }

    public function test_stl_metadata_is_extracted_and_info_reports_it(): void
    {
        Storage::fake((string) config('files.disk'));
        $user = User::factory()->create();
        $this->actingAs($user);

        $stl = $this->binaryStl();
        $file = $this->makeFile($user, 'cube.stl', 'files/cube.stl', 'model/stl', str_repeat('a', 64), $stl);

        // The observer ran the extraction inline (sync queue) on create.
        $meta = $file->fresh()->metadata;
        $this->assertSame('model', $meta['kind']);
        $this->assertSame('1', $meta['fields']['Triangles']);
        $this->assertSame('Binary', $meta['fields']['Format']);
        $this->assertSame('10.0 × 20.0 × 0.0', $meta['fields']['Size (mm)']);

        $this->getJson(route('files.rel.entries.info', $file->id))
            ->assertOk()
            ->assertJsonPath('metadata.kind', 'model')
            ->assertJsonPath('metadata.fields.Triangles', '1')
            ->assertJsonPath('sha256', str_repeat('a', 64))
            ->assertJsonPath('version', 0)
            ->assertJsonPath('duplicates', []);
    }

    public function test_info_reports_same_checksum_duplicates_owner_scoped(): void
    {
        Storage::fake((string) config('files.disk'));
        $user = User::factory()->create();
        $this->actingAs($user);

        $sha = str_repeat('b', 64);
        $a = $this->makeFile($user, 'a.txt', 'files/a.txt', 'text/plain', $sha, 'hello');
        $this->makeFile($user, 'copy.txt', 'files/copy.txt', 'text/plain', $sha, 'hello');
        // A different user's identical file must NOT appear.
        $other = User::factory()->create();
        $this->makeFile($other, 'x.txt', 'files/x.txt', 'text/plain', $sha, 'hello');

        $res = $this->getJson(route('files.rel.entries.info', $a->id))->assertOk();
        $res->assertJsonCount(1, 'duplicates')->assertJsonPath('duplicates.0.name', 'copy.txt');
    }

    public function test_info_is_owner_scoped(): void
    {
        Storage::fake((string) config('files.disk'));
        $owner = User::factory()->create();
        $file = $this->actingAs($owner)->makeFile($owner, 'p.txt', 'files/p.txt', 'text/plain', str_repeat('c', 64), 'x');

        $this->actingAs(User::factory()->create());
        $this->getJson(route('files.rel.entries.info', $file->id))->assertNotFound();
    }
}
