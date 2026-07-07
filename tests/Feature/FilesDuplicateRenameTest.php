<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesDuplicateRenameTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u, string $name, ?string $folderId = null): StoredFile
    {
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => $folderId,
            'name' => $name, 'blob' => $blob, 'size' => 1, 'mime' => 'text/plain'])->save();

        return $f;
    }

    public function test_duplicate_file_shares_blob_and_unique_name(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u, 'a.txt');

        $this->actingAs($u)->postJson(route('files.duplicate'), ['file_ids' => [$f->id]])->assertOk();

        $rows = StoredFile::withoutGlobalScopes()->where('user_id', $u->id)->get();
        $this->assertCount(2, $rows);
        $this->assertSame($f->blob, $rows->firstWhere('id', '!=', $f->id)->blob); // shared blob
        $this->assertStringContainsString('(', $rows->firstWhere('id', '!=', $f->id)->name); // suffixed
    }

    public function test_duplicate_folder_is_deep(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $folder = new FileFolder;
        $folder->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'parent_id' => null, 'name' => 'D'])->save();
        $this->file($u, 'inside.txt', $folder->id);

        $this->actingAs($u)->postJson(route('files.duplicate'), ['folder_ids' => [$folder->id]])->assertOk();

        $this->assertSame(2, FileFolder::withoutGlobalScopes()->count());
        $this->assertSame(2, StoredFile::withoutGlobalScopes()->count());
    }

    public function test_bulk_rename_find_replace_and_affix(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $a = $this->file($u, 'IMG_1.jpg');
        $b = $this->file($u, 'IMG_2.jpg');

        $this->actingAs($u)->postJson(route('files.bulk-rename'), [
            'file_ids' => [$a->id, $b->id], 'find' => 'IMG', 'replace' => 'Photo', 'prefix' => '2026-',
        ])->assertOk();

        $names = StoredFile::withoutGlobalScopes()->pluck('name')->sort()->values()->all();
        $this->assertSame(['2026-Photo_1.jpg', '2026-Photo_2.jpg'], $names);
    }
}
