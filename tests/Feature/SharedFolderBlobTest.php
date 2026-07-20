<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedFolderBlob;
use App\Models\SharedVault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedFolderBlobTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_row_persists_and_cascades_on_vault_delete(): void
    {
        $owner = User::factory()->create();
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->kind = 'folder';
        $vault->save();

        $blob = (string) Str::uuid();
        SharedFolderBlob::create(['blob' => $blob, 'vault_id' => $vault->id, 'owner_id' => $owner->id, 'size' => 42, 'created_at' => now()]);
        $this->assertNotNull(SharedFolderBlob::find($blob));

        $vault->delete();
        $this->assertNull(SharedFolderBlob::find($blob));
    }
}
