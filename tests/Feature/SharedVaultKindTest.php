<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedVaultKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_defaults_to_password_kind(): void
    {
        $this->signIn();
        $id = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk'])
            ->assertCreated()->json('id');
        $this->assertSame('password', SharedVault::find($id)->kind);
    }

    public function test_store_can_create_a_folder_vault(): void
    {
        $this->signIn();
        $id = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'folder'])
            ->assertCreated()->json('id');
        $this->assertSame('folder', SharedVault::find($id)->kind);
    }

    public function test_store_rejects_unknown_kind(): void
    {
        $this->signIn();
        $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_index_filters_by_kind(): void
    {
        $user = $this->signIn();
        $pw = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk'])->json('id');
        $fd = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'folder'])->json('id');

        $folderRows = $this->getJson(route('vaults.index', ['kind' => 'folder']))->assertOk()->json();
        $ids = array_column($folderRows, 'vault_id');
        $this->assertContains($fd, $ids);
        $this->assertNotContains($pw, $ids);

        $all = $this->getJson(route('vaults.index'))->assertOk()->json();
        $this->assertContains('kind', array_keys($all[0]));
    }

    public function test_index_marks_owner_flag(): void
    {
        // Owner creates a folder vault → their index row is owner=true.
        $owner = $this->signIn();
        $vaultId = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'folder'])->json('id');
        $ownerRow = collect($this->getJson(route('vaults.index'))->assertOk()->json())
            ->firstWhere('vault_id', $vaultId);
        $this->assertTrue($ownerRow['owner']);

        // An invited active member sees the same vault with owner=false.
        $member = User::factory()->create();
        SharedVaultMember::create([
            'vault_id' => $vaultId, 'user_id' => $member->id,
            'role' => 'editor', 'status' => 'active', 'wrapped_vault_key' => 'wk',
        ]);
        $this->app['auth']->forgetGuards();
        $memberRow = collect($this->actingAs($member)->getJson(route('vaults.index'))->assertOk()->json())
            ->firstWhere('vault_id', $vaultId);
        $this->assertFalse($memberRow['owner']);
    }
}
