<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The daily sweep reclaims stored bytes with NO ownership ledger row (leaked or
 * aborted uploads). It never removes bytes that have a row — those are either
 * referenced or handled by the client-driven reconcile — and it age-gates by
 * mtime so an in-flight upload is never destroyed.
 */
class SweepOrphanBlobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sweeps_only_unrecorded_aged_disk_bytes(): void
    {
        Storage::fake(config('files.disk'));
        $disk = Storage::disk(config('files.disk'));
        $user = User::factory()->create();

        $recorded = (string) Str::uuid();   // has a ledger row -> always kept
        $orphanOld = (string) Str::uuid();  // no row + aged past grace -> swept
        $orphanNew = (string) Str::uuid();  // no row but fresh -> kept (grace)
        foreach ([$recorded, $orphanOld, $orphanNew] as $b) {
            $disk->put('files/'.$b, 'ciphertext');
        }
        FileBlob::create(['blob' => $recorded, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        // Age the orphan file's bytes past the 24h grace window.
        touch($disk->path('files/'.$orphanOld), time() - 3 * 24 * 3600);

        $this->artisan('files:sweep-orphans')->assertSuccessful();

        $disk->assertExists('files/'.$recorded);
        $disk->assertExists('files/'.$orphanNew);
        $disk->assertMissing('files/'.$orphanOld);
    }
}
