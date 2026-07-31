<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedFolderBlob;
use App\Models\SharedVault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SweepOrphanSharedFolderBlobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sweeps_only_ledgerless_aged_bytes(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));
        $owner = User::factory()->create();
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->kind = 'folder';
        $vault->save();

        $known = (string) Str::uuid();
        $orphan = (string) Str::uuid();
        $disk->put('shared-folders/'.$known, 'x');
        $disk->put('shared-folders/'.$orphan, 'x');
        SharedFolderBlob::create(['blob' => $known, 'vault_id' => $vault->id, 'owner_id' => $owner->id, 'size' => 1, 'created_at' => now()]);
        // Age the orphan file past the grace window.
        touch($disk->path('shared-folders/'.$orphan), now()->subDays(3)->getTimestamp());

        $this->artisan('shared-folders:sweep-orphans')->assertSuccessful();

        $disk->assertExists('shared-folders/'.$known);
        // The orphan has no ledger row and is aged → swept.
        // (In the fake disk, lastModified honours the touch above.)
        $disk->assertMissing('shared-folders/'.$orphan);
    }
}
