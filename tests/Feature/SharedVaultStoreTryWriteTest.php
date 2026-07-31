<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedVault;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SharedVaultStore::tryWrite must persist the FIRST write (no row yet) instead of
 * a bare update() no-op that reports success while silently losing the manifest.
 */
class SharedVaultStoreTryWriteTest extends TestCase
{
    use RefreshDatabase;

    private function vault(): SharedVault
    {
        $owner = User::factory()->create();
        $v = new SharedVault;
        $v->owner_id = $owner->id;
        $v->save();

        return $v;
    }

    public function test_first_write_with_no_existing_row_persists(): void
    {
        $vault = $this->vault();

        $result = SharedVaultStore::tryWrite($vault->id, 'sealed-1', 0);

        $this->assertFalse($result['conflict']);
        $this->assertSame(1, $result['version']);
        $this->assertDatabaseHas('shared_vault_stores', [
            'vault_id' => $vault->id,
            'sealed_manifest' => 'sealed-1',
            'version' => 1,
        ]);
    }

    public function test_subsequent_write_updates_the_existing_row(): void
    {
        $vault = $this->vault();
        SharedVaultStore::tryWrite($vault->id, 'sealed-1', 0);

        $result = SharedVaultStore::tryWrite($vault->id, 'sealed-2', 1);

        $this->assertFalse($result['conflict']);
        $this->assertSame(2, $result['version']);
        $this->assertSame('sealed-2', SharedVaultStore::where('vault_id', $vault->id)->value('sealed_manifest'));
        $this->assertSame(1, SharedVaultStore::where('vault_id', $vault->id)->count());
    }

    public function test_stale_version_is_a_conflict(): void
    {
        $vault = $this->vault();
        SharedVaultStore::tryWrite($vault->id, 'sealed-1', 0);

        $result = SharedVaultStore::tryWrite($vault->id, 'sealed-stale', 0);

        $this->assertTrue($result['conflict']);
        $this->assertSame(1, $result['version']);
        $this->assertSame('sealed-1', SharedVaultStore::where('vault_id', $vault->id)->value('sealed_manifest'));
    }
}
