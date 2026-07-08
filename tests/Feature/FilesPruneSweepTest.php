<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesPruneSweepTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u, string $blob): void
    {
        (new StoredFile)->forceFill([
            'id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => 'k',
            'blob' => $blob, 'mime' => 'text/plain', 'size' => 4,
        ])->save();
    }

    public function test_prune_sweeps_unreferenced_disk_blobs_but_keeps_referenced(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files', 'files.blob_orphan_grace_hours' => 0]);
        $u = User::factory()->create();

        // A leaked, unreferenced blob file (no row, no version, no upload record).
        $orphan = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$orphan, 'leak');

        // A blob referenced by a live file — must survive.
        $kept = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$kept, 'keep');
        $this->file($u, $kept);

        // A blob referenced only by a version — must also survive.
        $versioned = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$versioned, 'ver');
        $vf = (new StoredFile);
        $vf->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => 'v', 'blob' => (string) Str::uuid(), 'mime' => 'text/plain', 'size' => 1])->save();
        FileVersion::create(['id' => (string) Str::uuid(), 'file_id' => $vf->id, 'user_id' => $u->id,
            'name' => 'v', 'mime' => 'text/plain', 'size' => 3, 'blob' => $versioned, 'created_at' => now()]);

        $this->artisan('files:prune-trash')->assertSuccessful();

        Storage::disk('files')->assertMissing('files/'.$orphan);
        Storage::disk('files')->assertExists('files/'.$kept);
        Storage::disk('files')->assertExists('files/'.$versioned);
    }
}
